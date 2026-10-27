<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Ticket;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Per-ticket access rules.
 *  - customers: only their own tickets
 *  - agents:    tickets assigned to them or still unassigned
 *  - admins:    everything.
 *
 * @extends Voter<string, Ticket>
 */
final class TicketVoter extends Voter
{
    public const VIEW = 'TICKET_VIEW';
    public const COMMENT = 'TICKET_COMMENT';
    public const MANAGE = 'TICKET_MANAGE'; // assign, change status/priority, internal notes

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Ticket && \in_array($attribute, [self::VIEW, self::COMMENT, self::MANAGE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (\in_array(User::ROLE_ADMIN, $user->getRoles(), true)) {
            return true;
        }

        $isOwner = $subject->getRequester() === $user;

        if ($user->isAgent()) {
            $assignee = $subject->getAssignee();
            $isHandler = null === $assignee || $assignee === $user;

            return match ($attribute) {
                self::VIEW, self::COMMENT, self::MANAGE => $isHandler,
                default => false,
            };
        }

        return match ($attribute) {
            self::VIEW => $isOwner,
            self::COMMENT => $isOwner && $subject->getStatus()->isActive(),
            default => false,
        };
    }
}
