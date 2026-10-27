<?php

declare(strict_types=1);

namespace App\Message;

final readonly class TicketSlaBreached
{
    public function __construct(public int $ticketId)
    {
    }
}
