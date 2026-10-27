<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Ticket;
use App\Enum\TicketPriority;
use DateTimeImmutable;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SlaCommandTest extends WebTestCase
{
    public function testBreachedTicketsAreNotifiedExactlyOnce(): void
    {
        $customer = $this->makeUser('customer@example.test');
        $this->makeUser('agent@example.test', ['ROLE_AGENT']);
        $ticket = (new Ticket($customer, TicketPriority::Urgent))->setTitle('Server down')->setDescription('Everything is down, help.');
        // pretend it was created two days ago
        $ref = new ReflectionProperty(Ticket::class, 'responseDueAt');
        $ref->setValue($ticket, new DateTimeImmutable('-1 day'));
        $this->em->persist($ticket);
        $this->em->flush();

        $app = new Application(static::bootKernel());
        $tester = new CommandTester($app->find('app:tickets:check-sla'));

        $tester->execute([]);
        self::assertStringContainsString('1 ticket(s) breached', $tester->getDisplay());

        $tester->execute([]);
        self::assertStringContainsString('0 ticket(s) breached', $tester->getDisplay());
    }
}
