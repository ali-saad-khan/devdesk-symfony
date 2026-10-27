<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketPriority;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;

abstract class WebTestCase extends BaseWebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    /** @param list<string> $roles */
    protected function makeUser(string $email, array $roles = [], bool $verified = true): User
    {
        $user = (new User())->setEmail($email)->setName(ucfirst(strtok($email, '@') ?: 'user'))->setRoles($roles)->setIsVerified($verified);
        $user->setPassword('unused-in-tests');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function makeTicket(User $requester, string $title = 'Printer is on fire', ?User $assignee = null): Ticket
    {
        $ticket = (new Ticket($requester, TicketPriority::Normal))->setTitle($title)->setDescription('It really is, please help soon.')->assignTo($assignee);
        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }
}
