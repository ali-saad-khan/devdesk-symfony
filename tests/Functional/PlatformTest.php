<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class PlatformTest extends WebTestCase
{
    public function testSecurityHeadersAreSent(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHasHeader('Content-Security-Policy');
    }

    public function testCronEndpointRejectsMissingOrWrongSecret(): void
    {
        $this->client->request('GET', '/cron/sla');
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/cron/sla', server: ['HTTP_AUTHORIZATION' => 'Bearer wrong']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testCronEndpointRunsWithTheRightSecret(): void
    {
        $this->client->request('GET', '/cron/sla', server: ['HTTP_AUTHORIZATION' => 'Bearer test-cron-secret']);

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"breached":0}', (string) $this->client->getResponse()->getContent());
    }
}
