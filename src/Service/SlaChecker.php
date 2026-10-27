<?php

declare(strict_types=1);

namespace App\Service;

use App\Message\TicketSlaBreached;
use App\Repository\TicketRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/** Finds tickets that missed their first-response deadline and notifies agents once per ticket. */
final class SlaChecker
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function run(?DateTimeImmutable $now = null): int
    {
        $breaches = $this->tickets->findNewSlaBreaches($now ?? new DateTimeImmutable());

        foreach ($breaches as $ticket) {
            $ticket->markSlaBreachNotified();
            $this->em->flush(); // flag first so a failing dispatch never re-notifies in a loop
            $this->bus->dispatch(new TicketSlaBreached((int) $ticket->getId()));
        }

        return \count($breaches);
    }
}
