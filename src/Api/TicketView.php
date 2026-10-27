<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Ticket;

/** The public JSON shape of a ticket. Kept explicit so entity changes never leak into the API by accident. */
final class TicketView
{
    /** @return array<string, mixed> */
    public static function from(Ticket $ticket): array
    {
        return [
            'id' => $ticket->getId(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'status' => $ticket->getStatus()->value,
            'priority' => $ticket->getPriority()->value,
            'requester' => $ticket->getRequester()->getName(),
            'assignee' => $ticket->getAssignee()?->getName(),
            'createdAt' => $ticket->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $ticket->getUpdatedAt()->format(\DATE_ATOM),
            'responseDueAt' => $ticket->getResponseDueAt()?->format(\DATE_ATOM),
            'slaBreached' => $ticket->isOverdue(),
        ];
    }
}
