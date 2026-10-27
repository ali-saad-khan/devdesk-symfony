<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Ticket;
use App\Entity\User;
use App\Message\TicketCreated;
use App\Message\TicketSlaBreached;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

/** Emails agents when a ticket is opened and when one misses its response deadline. */
final class NotifyAgentsHandler
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly UserRepository $users,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.mail_from%')] private readonly string $from,
    ) {
    }

    #[AsMessageHandler]
    public function onCreated(TicketCreated $message): void
    {
        $ticket = $this->tickets->find($message->ticketId);
        if ($ticket instanceof Ticket) {
            $this->notify($ticket, \sprintf('New ticket #%d: %s', $ticket->getId(), $ticket->getTitle()), 'created');
        }
    }

    #[AsMessageHandler]
    public function onSlaBreached(TicketSlaBreached $message): void
    {
        $ticket = $this->tickets->find($message->ticketId);
        if ($ticket instanceof Ticket) {
            $this->notify($ticket, \sprintf('SLA breached on ticket #%d: %s', $ticket->getId(), $ticket->getTitle()), 'sla_breached');
        }
    }

    private function notify(Ticket $ticket, string $subject, string $kind): void
    {
        $assignee = $ticket->getAssignee();
        /** @var list<User> $recipients */
        $recipients = null !== $assignee ? [$assignee] : $this->users->findAgents();

        foreach ($recipients as $agent) {
            $email = (new TemplatedEmail())
                ->from(new Address($this->from, 'DevDesk'))
                ->to(new Address($agent->getEmail(), $agent->getName()))
                ->subject($subject)
                ->htmlTemplate('email/ticket_notification.html.twig')
                ->context(['ticket' => $ticket, 'kind' => $kind, 'agent' => $agent]);
            $this->mailer->send($email);
        }

        $this->logger->info('Agents notified', ['ticket' => $ticket->getId(), 'kind' => $kind, 'recipients' => \count($recipients)]);
    }
}
