<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;

final class AccessControlTest extends WebTestCase
{
    public function testAnonymousIsSentToLogin(): void
    {
        $this->client->request('GET', '/tickets');

        self::assertResponseRedirects('/login');
    }

    public function testHealthEndpointIsPublic(): void
    {
        $this->client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"status":"ok","database":"ok"}', (string) $this->client->getResponse()->getContent());
    }

    public function testCustomerSeesOnlyOwnTickets(): void
    {
        $alice = $this->makeUser('alice@example.test');
        $bob = $this->makeUser('bob@example.test');
        $this->makeTicket($alice, 'Alice ticket');
        $bobs = $this->makeTicket($bob, 'Bob secret ticket');

        $this->client->loginUser($alice);
        $this->client->request('GET', '/tickets');
        self::assertSelectorTextContains('table', 'Alice ticket');
        self::assertSelectorTextNotContains('table', 'Bob secret ticket');

        $this->client->request('GET', '/tickets/'.$bobs->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testAgentCannotOpenTicketAssignedToAnotherAgent(): void
    {
        $customer = $this->makeUser('customer@example.test');
        $agentA = $this->makeUser('a@example.test', [User::ROLE_AGENT]);
        $agentB = $this->makeUser('b@example.test', [User::ROLE_AGENT]);
        $ticket = $this->makeTicket($customer, 'Needs A', $agentA);

        $this->client->loginUser($agentB);
        $this->client->request('GET', '/tickets/'.$ticket->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($agentA);
        $this->client->request('GET', '/tickets/'.$ticket->getId());
        self::assertResponseIsSuccessful();
    }

    public function testCustomerCannotAssignOrChangeStatusAsAgent(): void
    {
        $customer = $this->makeUser('customer@example.test');
        $ticket = $this->makeTicket($customer);

        $this->client->loginUser($customer);
        $this->client->request('POST', \sprintf('/tickets/%d/assign', $ticket->getId()), ['assignee' => 0]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', \sprintf('/tickets/%d/transition/resolve', $ticket->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testInternalNotesAreHiddenFromCustomers(): void
    {
        $customer = $this->makeUser('customer@example.test');
        $agent = $this->makeUser('agent@example.test', [User::ROLE_AGENT]);
        $ticket = $this->makeTicket($customer, 'With notes', $agent);
        $ticket->addComment(new \App\Entity\Comment($ticket, $agent, 'Visible reply'));
        $ticket->addComment(new \App\Entity\Comment($ticket, $agent, 'Customer is difficult', true));
        $this->em->flush();

        $this->client->loginUser($customer);
        $this->client->request('GET', '/tickets/'.$ticket->getId());
        self::assertSelectorTextContains('body', 'Visible reply');
        self::assertSelectorTextNotContains('body', 'Customer is difficult');

        $this->client->loginUser($agent);
        $this->client->request('GET', '/tickets/'.$ticket->getId());
        self::assertSelectorTextContains('body', 'Customer is difficult');
    }
}
