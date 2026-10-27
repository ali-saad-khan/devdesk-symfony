<?php

declare(strict_types=1);

namespace App\Enum;

enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Hours an agent has to respond before the ticket breaches its SLA. */
    public function slaHours(): int
    {
        return match ($this) {
            self::Urgent => 4,
            self::High => 8,
            self::Normal => 24,
            self::Low => 72,
        };
    }
}
