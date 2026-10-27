<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;
use App\Form\CommentType;
use App\Form\TicketType;
use App\Message\TicketCreated;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Security\TicketVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/tickets')]
final class TicketController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkflowInterface $ticketStatusStateMachine,
    ) {
    }

    #[Route('', name: 'app_ticket_index', methods: ['GET'])]
    public function index(Request $request, TicketRepository $tickets, #[CurrentUser] User $user): Response
    {
        $status = TicketStatus::tryFrom((string) $request->query->get('status'));
        $search = $request->query->get('q');

        return $this->render('ticket/index.html.twig', [
            'tickets' => $tickets->findVisibleTo($user, $status, \is_string($search) ? $search : null),
            'status' => $status,
            'q' => \is_string($search) ? $search : '',
            'statuses' => TicketStatus::cases(),
        ]);
    }

    #[Route('/new', name: 'app_ticket_new', methods: ['GET', 'POST'])]
    public function new(Request $request, MessageBusInterface $bus, #[CurrentUser] User $user): Response
    {
        $ticket = new Ticket($user);
        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($ticket);
            $this->em->flush();
            $bus->dispatch(new TicketCreated((int) $ticket->getId()));
            $this->addFlash('success', 'Ticket created.');

            return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
        }

        return $this->render('ticket/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_ticket_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(TicketVoter::VIEW, subject: 'ticket')]
    public function show(Ticket $ticket, UserRepository $users, #[CurrentUser] User $user): Response
    {
        $canComment = $this->isGranted(TicketVoter::COMMENT, $ticket);
        $comments = $ticket->getComments()->filter(static fn (Comment $c): bool => !$c->isInternal() || $user->isAgent());

        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
            'comments' => $comments,
            'commentForm' => $canComment ? $this->createForm(CommentType::class, null, [
                'allow_internal' => $user->isAgent(),
                'action' => $this->generateUrl('app_ticket_comment', ['id' => $ticket->getId()]),
            ]) : null,
            'transitions' => $this->isGranted(TicketVoter::MANAGE, $ticket) || $this->canRequesterTransition($ticket, $user)
                ? $this->availableTransitions($ticket, $user) : [],
            'agents' => $this->isGranted(TicketVoter::MANAGE, $ticket) ? $users->findAgents() : [],
        ]);
    }

    #[Route('/{id}/comment', name: 'app_ticket_comment', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TicketVoter::COMMENT, subject: 'ticket')]
    public function comment(Request $request, Ticket $ticket, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(CommentType::class, null, ['allow_internal' => $user->isAgent()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{body: string} $data */
            $data = $form->getData();
            $internal = $form->has('internal') && (bool) $form->get('internal')->getData();
            $ticket->addComment(new Comment($ticket, $user, $data['body'], $internal));
            $this->em->flush();
            $this->addFlash('success', $internal ? 'Internal note added.' : 'Reply posted.');
        } else {
            $this->addFlash('error', 'Write something before sending.');
        }

        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/{id}/transition/{transition}', name: 'app_ticket_transition', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TicketVoter::VIEW, subject: 'ticket')]
    public function transition(Request $request, Ticket $ticket, string $transition, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessCsrf($request, 'transition'.$ticket->getId());

        if (!\in_array($transition, $this->availableTransitions($ticket, $user), true)) {
            throw $this->createAccessDeniedException('That status change is not allowed.');
        }

        $this->ticketStatusStateMachine->apply($ticket, $transition);
        $this->em->flush();
        $this->addFlash('success', \sprintf('Ticket is now "%s".', $ticket->getStatus()->label()));

        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/{id}/assign', name: 'app_ticket_assign', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TicketVoter::MANAGE, subject: 'ticket')]
    public function assign(Request $request, Ticket $ticket, UserRepository $users): Response
    {
        $this->denyUnlessCsrf($request, 'assign'.$ticket->getId());

        $assigneeId = $request->request->getInt('assignee');
        $assignee = $assigneeId > 0 ? $users->find($assigneeId) : null;
        if (null !== $assignee && !$assignee->isAgent()) {
            throw $this->createAccessDeniedException('Tickets can only be assigned to agents.');
        }

        $ticket->assignTo($assignee);
        $this->em->flush();
        $this->addFlash('success', null === $assignee ? 'Ticket unassigned.' : \sprintf('Assigned to %s.', $assignee->getName()));

        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    /**
     * Agents may use any enabled transition. Customers may only close their own resolved ticket or reopen it.
     *
     * @return list<string>
     */
    private function availableTransitions(Ticket $ticket, User $user): array
    {
        $names = array_map(static fn ($t) => $t->getName(), $this->ticketStatusStateMachine->getEnabledTransitions($ticket));
        if ($user->isAgent() && $this->isGranted(TicketVoter::MANAGE, $ticket)) {
            return array_values($names);
        }

        return $ticket->getRequester() === $user
            ? array_values(array_intersect($names, ['close', 'reopen']))
            : [];
    }

    private function canRequesterTransition(Ticket $ticket, User $user): bool
    {
        return $ticket->getRequester() === $user;
    }

    private function denyUnlessCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
