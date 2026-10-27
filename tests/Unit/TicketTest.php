<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Comment;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use PHPUnit\Framework\TestCase;

final class TicketTest extends TestCase
{
    public function testResponseDeadlineFollowsPriority(): void
    {
        $ticket = new Ticket(new User(), TicketPriority::Urgent);

        $due = $ticket->getResponseDueAt();
        self::assertNotNull($due);
        self::assertSame(4 * 3600, $due->getTimestamp() - $ticket->getCreatedAt()->getTimestamp());

        $ticket->setPriority(TicketPriority::Low);
        self::assertSame(72 * 3600, $ticket->getResponseDueAt()?->getTimestamp() - $ticket->getCreatedAt()->getTimestamp());
    }

    public function testOverdueOnlyWhileActiveAndUnanswered(): void
    {
        $ticket = new Ticket(new User(), TicketPriority::Urgent);
        $later = $ticket->getCreatedAt()->modify('+5 hours');

        self::assertFalse($ticket->isOverdue($ticket->getCreatedAt()->modify('+1 hour')));
        self::assertTrue($ticket->isOverdue($later));

        $ticket->setStatus(TicketStatus::Closed);
        self::assertFalse($ticket->isOverdue($later), 'closed tickets cannot breach');
    }

    public function testAgentReplyStopsTheSlaClockButCustomerReplyDoesNot(): void
    {
        $customer = new User();
        $agent = (new User())->setRoles([User::ROLE_AGENT]);
        $ticket = new Ticket($customer, TicketPriority::Normal);

        $ticket->addComment(new Comment($ticket, $customer, 'Any news?'));
        self::assertNotNull($ticket->getResponseDueAt());

        $ticket->addComment(new Comment($ticket, $agent, 'Internal only', true));
        self::assertNotNull($ticket->getResponseDueAt(), 'internal notes are not a reply to the customer');

        $ticket->addComment(new Comment($ticket, $agent, 'Hello, looking into it.'));
        self::assertNull($ticket->getResponseDueAt());
    }

    public function testCustomersCannotWriteInternalNotes(): void
    {
        $customer = new User();
        $comment = new Comment(new Ticket($customer), $customer, 'sneaky', true);

        self::assertFalse($comment->isInternal());
    }
}
