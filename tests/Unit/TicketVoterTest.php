<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;
use App\Security\TicketVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class TicketVoterTest extends TestCase
{
    /** @return iterable<string, array{string, string, int}> */
    public static function cases(): iterable
    {
        yield 'owner can view' => ['owner', TicketVoter::VIEW, VoterInterface::ACCESS_GRANTED];
        yield 'owner can comment' => ['owner', TicketVoter::COMMENT, VoterInterface::ACCESS_GRANTED];
        yield 'owner cannot manage' => ['owner', TicketVoter::MANAGE, VoterInterface::ACCESS_DENIED];
        yield 'stranger cannot view' => ['stranger', TicketVoter::VIEW, VoterInterface::ACCESS_DENIED];
        yield 'agent can view unassigned' => ['agent', TicketVoter::VIEW, VoterInterface::ACCESS_GRANTED];
        yield 'agent can manage unassigned' => ['agent', TicketVoter::MANAGE, VoterInterface::ACCESS_GRANTED];
        yield 'admin can manage' => ['admin', TicketVoter::MANAGE, VoterInterface::ACCESS_GRANTED];
    }

    #[DataProvider('cases')]
    public function testVotes(string $who, string $attribute, int $expected): void
    {
        $users = [
            'owner' => new User(),
            'stranger' => new User(),
            'agent' => (new User())->setRoles([User::ROLE_AGENT]),
            'admin' => (new User())->setRoles([User::ROLE_ADMIN]),
        ];
        $ticket = new Ticket($users['owner']);

        self::assertSame($expected, $this->vote($users[$who], $attribute, $ticket));
    }

    public function testAgentCannotTouchTicketAssignedToSomeoneElse(): void
    {
        $owner = new User();
        $mine = (new User())->setRoles([User::ROLE_AGENT]);
        $other = (new User())->setRoles([User::ROLE_AGENT]);
        $ticket = (new Ticket($owner))->assignTo($other);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($mine, TicketVoter::VIEW, $ticket));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($other, TicketVoter::VIEW, $ticket));
    }

    public function testOwnerCannotCommentOnClosedTicket(): void
    {
        $owner = new User();
        $ticket = (new Ticket($owner))->setStatus(TicketStatus::Closed);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($owner, TicketVoter::COMMENT, $ticket));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($owner, TicketVoter::VIEW, $ticket));
    }

    public function testAnonymousIsDenied(): void
    {
        $voter = new TicketVoter();
        $token = $this->createStub(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, new Ticket(new User()), [TicketVoter::VIEW]));
    }

    private function vote(User $user, string $attribute, Ticket $ticket): int
    {
        return (new TicketVoter())->vote(new UsernamePasswordToken($user, 'main', $user->getRoles()), $ticket, [$attribute]);
    }
}
