<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\CreateTicketRequest;
use App\Api\TicketView;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;
use App\Message\TicketCreated;
use App\Repository\TicketRepository;
use App\Security\TicketVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tickets')]
final class ApiTicketController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.api')] private readonly RateLimiterFactory $apiLimiter,
    ) {
    }

    #[Route('', name: 'api_ticket_list', methods: ['GET'])]
    public function list(Request $request, TicketRepository $tickets, #[CurrentUser] User $user): JsonResponse
    {
        if ($limited = $this->throttle($user)) {
            return $limited;
        }

        $status = TicketStatus::tryFrom((string) $request->query->get('status'));
        $data = array_map(TicketView::from(...), $tickets->findVisibleTo($user, $status));

        return $this->json(['data' => $data, 'count' => \count($data)]);
    }

    #[Route('/{id}', name: 'api_ticket_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(TicketVoter::VIEW, subject: 'ticket')]
    public function show(Ticket $ticket, #[CurrentUser] User $user): JsonResponse
    {
        return $this->throttle($user) ?? $this->json(TicketView::from($ticket));
    }

    #[Route('', name: 'api_ticket_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateTicketRequest $payload,
        #[CurrentUser] User $user,
        EntityManagerInterface $em,
        MessageBusInterface $bus,
    ): JsonResponse {
        if ($limited = $this->throttle($user)) {
            return $limited;
        }

        $ticket = (new Ticket($user, $payload->priority))->setTitle($payload->title)->setDescription($payload->description);
        $em->persist($ticket);
        $em->flush();
        $bus->dispatch(new TicketCreated((int) $ticket->getId()));

        return $this->json(TicketView::from($ticket), Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_ticket_show', ['id' => $ticket->getId()]),
        ]);
    }

    private function throttle(User $user): ?JsonResponse
    {
        $limit = $this->apiLimiter->create((string) $user->getId())->consume();
        if ($limit->isAccepted()) {
            return null;
        }

        return $this->json(['error' => 'Too many requests.'], Response::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()),
        ]);
    }
}
