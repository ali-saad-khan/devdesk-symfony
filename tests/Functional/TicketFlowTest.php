<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;

final class TicketFlowTest extends WebTestCase
{
    public function testCustomerOpensTicketAndItIsQueuedForAgents(): void
    {
        $customer = $this->makeUser('customer@example.test');
        $this->client->loginUser($customer);

        $crawler = $this->client->request('GET', '/tickets/new');
        $form = $crawler->selectButton('Submit ticket')->form([
            'ticket[title]' => 'My account is locked',
            'ticket[description]' => 'I tried three times and now it says locked.',
            'ticket[priority]' => 'high',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $ticket = $this->em->getRepository(Ticket::class)->findOneBy(['title' => 'My account is locked']);
        self::assertInstanceOf(Ticket::class, $ticket);
        self::assertSame(TicketStatus::Open, $ticket->getStatus());
        self::assertSame($customer->getId(), $ticket->getRequester()->getId());
        self::assertCount(1, $this->getMessengerTransport()->getSent());
    }

    public function testInvalidTicketIsRejected(): void
    {
        $this->client->loginUser($this->makeUser('customer@example.test'));
        $crawler = $this->client->request('GET', '/tickets/new');
        $this->client->submit($crawler->selectButton('Submit ticket')->form(['ticket[title]' => 'x', 'ticket[description]' => 'short']));

        self::assertResponseStatusCodeSame(422);
    }

    public function testAgentWorksTicketThroughTheWorkflow(): void
    {
        $customer = $this->makeUser('customer@example.test');
        $agent = $this->makeUser('agent@example.test', [User::ROLE_AGENT]);
        $ticket = $this->makeTicket($customer);

        $this->client->loginUser($agent);
        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getId());
        $this->client->submit($crawler->selectButton('Start')->form());
        $this->em->clear();
        self::assertSame(TicketStatus::InProgress, $this->em->find(Ticket::class, $ticket->getId())?->getStatus());

        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getId());
        $this->client->submit($crawler->selectButton('Resolve')->form());
        $this->em->clear();
        self::assertSame(TicketStatus::Resolved, $this->em->find(Ticket::class, $ticket->getId())?->getStatus());
    }

    public function testCustomerCannotJumpStraightToClosed(): void
    {
        $customer = $this->makeUser('customer@example.test');
        $ticket = $this->makeTicket($customer); // still open: "close" is not an enabled transition

        $this->client->loginUser($customer);
        $this->client->request('GET', '/tickets/'.$ticket->getId());
        self::assertSelectorNotExists('form[action$="/transition/close"]');
    }

    public function testRegistrationRequiresEmailVerificationBeforeLogin(): void
    {
        $user = $this->makeUser('new@example.test', [], false);
        $user->setPassword(static::getContainer()->get('security.user_password_hasher')->hashPassword($user, 'a-long-enough-password'));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Sign in')->form(['_username' => 'new@example.test', '_password' => 'a-long-enough-password']));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash.error', 'verify your email');
    }

    private function getMessengerTransport(): \Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport
    {
        /** @var \Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport $t */
        $t = static::getContainer()->get('messenger.transport.async');

        return $t;
    }
}
