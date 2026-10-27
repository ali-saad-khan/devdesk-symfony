<?php

declare(strict_types=1);

namespace App\Api;

use App\Enum\TicketPriority;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateTicketRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 200)]
        public string $title = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: 10, max: 10000)]
        public string $description = '',
        public TicketPriority $priority = TicketPriority::Normal,
    ) {
    }
}
