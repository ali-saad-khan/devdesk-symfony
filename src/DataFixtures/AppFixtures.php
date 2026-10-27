<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Demo data. All demo accounts share the password below, so never load these fixtures into a real production database. */
final class AppFixtures extends Fixture
{
    public const DEMO_PASSWORD = 'devdesk-demo-2026';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->user($manager, 'admin@devdesk.test', 'Alex Admin', [User::ROLE_ADMIN]);
        $agent = $this->user($manager, 'agent@devdesk.test', 'Sam Agent', [User::ROLE_AGENT]);
        $anna = $this->user($manager, 'customer@devdesk.test', 'Casey Customer', []);
        $ben = $this->user($manager, 'ben@devdesk.test', 'Ben Buyer', []);

        $samples = [
            [$anna, 'Cannot reset my password', 'The reset email never arrives, I checked spam twice.', TicketPriority::High, TicketStatus::Open, null],
            [$anna, 'Invoice shows the wrong VAT number', 'Our VAT number changed in March; please update the invoice template.', TicketPriority::Normal, TicketStatus::InProgress, $agent],
            [$ben, 'Dashboard is slow on Mondays', 'Loading the weekly report takes over a minute since the last release.', TicketPriority::Urgent, TicketStatus::Open, null],
            [$ben, 'Feature request: CSV export', 'It would help to export the ticket list as CSV for our monthly review.', TicketPriority::Low, TicketStatus::Resolved, $agent],
        ];

        foreach ($samples as [$requester, $title, $description, $priority, $status, $assignee]) {
            $ticket = new Ticket($requester, $priority);
            $ticket->setTitle($title)->setDescription($description)->assignTo($assignee)->setStatus($status);
            $manager->persist($ticket);

            if (null !== $assignee) {
                $ticket->addComment(new Comment($ticket, $assignee, 'Thanks for reaching out, I am looking into this now.'));
                $ticket->addComment(new Comment($ticket, $assignee, 'Waiting on the billing team for confirmation.', true));
            }
        }

        $manager->flush();
    }

    /** @param list<string> $roles */
    private function user(ObjectManager $manager, string $email, string $name, array $roles): User
    {
        $user = (new User())->setEmail($email)->setName($name)->setRoles($roles)->setIsVerified(true);
        $user->setPassword($this->hasher->hashPassword($user, self::DEMO_PASSWORD));
        $manager->persist($user);

        return $user;
    }
}
