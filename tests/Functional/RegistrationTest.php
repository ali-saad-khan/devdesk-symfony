<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;

final class RegistrationTest extends WebTestCase
{
    public function testRegisteringCreatesAnUnverifiedUserAndQueuesTheConfirmationEmail(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Register')->form([
            'registration[name]' => 'Nora New',
            'registration[email]' => 'Nora@Example.test',
            'registration[plainPassword][first]' => 'correct-horse-battery-staple-91',
            'registration[plainPassword][second]' => 'correct-horse-battery-staple-91',
        ]));

        self::assertResponseRedirects('/login');
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'nora@example.test']);
        self::assertInstanceOf(User::class, $user, 'email is normalised to lower case');
        self::assertFalse($user->isVerified());
        self::assertNotSame('correct-horse-battery-staple-91', $user->getPassword(), 'password is hashed');
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $this->makeUser('taken@example.test');

        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Register')->form([
            'registration[name]' => 'Dup',
            'registration[email]' => 'taken@example.test',
            'registration[plainPassword][first]' => 'correct-horse-battery-staple-91',
            'registration[plainPassword][second]' => 'correct-horse-battery-staple-91',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'already an account');
    }
}
