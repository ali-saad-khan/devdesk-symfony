<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\ApiTokenHandler;

final class ApiTest extends WebTestCase
{
    public function testRequiresToken(): void
    {
        $this->client->request('GET', '/api/tickets');

        self::assertResponseStatusCodeSame(401);
    }

    public function testBadTokenIsRejected(): void
    {
        $this->client->request('GET', '/api/tickets', server: ['HTTP_AUTHORIZATION' => 'Bearer nope']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateAndListWithToken(): void
    {
        $user = $this->makeUser('api@example.test');
        $user->setApiTokenHash(ApiTokenHandler::hash('dd_testtoken'));
        $this->em->flush();
        $auth = ['HTTP_AUTHORIZATION' => 'Bearer dd_testtoken', 'CONTENT_TYPE' => 'application/json'];

        $this->client->request('POST', '/api/tickets', server: $auth, content: json_encode(['title' => 'API made ticket', 'description' => 'Created through the REST API.', 'priority' => 'urgent'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        self::assertResponseHasHeader('Location');

        $this->client->request('GET', '/api/tickets', server: $auth);
        self::assertResponseIsSuccessful();
        /** @var array{count: int, data: list<array{title: string, priority: string}>} $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['count']);
        self::assertSame('urgent', $body['data'][0]['priority']);
    }

    public function testValidationErrorsReturn422(): void
    {
        $user = $this->makeUser('api@example.test');
        $user->setApiTokenHash(ApiTokenHandler::hash('dd_testtoken'));
        $this->em->flush();

        $this->client->request('POST', '/api/tickets', server: ['HTTP_AUTHORIZATION' => 'Bearer dd_testtoken', 'CONTENT_TYPE' => 'application/json'], content: '{"title":"x","description":"y"}');

        self::assertResponseStatusCodeSame(422);
    }

    public function testCannotReadSomeoneElsesTicket(): void
    {
        $owner = $this->makeUser('owner@example.test');
        $ticket = $this->makeTicket($owner);
        $other = $this->makeUser('other@example.test');
        $other->setApiTokenHash(ApiTokenHandler::hash('dd_other'));
        $this->em->flush();

        $this->client->request('GET', '/api/tickets/'.$ticket->getId(), server: ['HTTP_AUTHORIZATION' => 'Bearer dd_other']);

        self::assertResponseStatusCodeSame(403);
    }
}
