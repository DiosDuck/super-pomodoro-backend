<?php

declare(strict_types=1);

namespace App\Tests\Authentication\EventListener;

use App\Authentication\Entity\User;
use App\Authentication\Repository\TokenVerificationRepository;
use App\Tests\Utils\Controller\CleanWebTestCase;
use DateTimeImmutable;

class AuthenticationSubscriberTest extends CleanWebTestCase {
    private DateTimeImmutable $dateTimeInitialized;

    public function setUp(): void
    {
        parent::setUp();

        $this->dateTimeInitialized = new DateTimeImmutable("-10 minutes");

        $user = new User();
        $user->setUsername('username');
        $user->setPassword('password');
        $user->setDisplayName('Username');
        $user->setEmail('user@email.com');
        $user->setActivatedAt($this->dateTimeInitialized);
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);
        $user->setLastLoggedIn($this->dateTimeInitialized);

        $this->saveUser($user);
    }

    public function testUpdatedLastLoggedIn(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'username' => 'username',
                'password' => 'password',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();

        $user = $this->getUser('username');
        $this->assertEquals($this->dateTimeInitialized, $user->getActivatedAt());
        $this->assertNotEquals($this->dateTimeInitialized, $user->getLastLoggedIn());
    }

    // TODO: implement by hand the new refresh token
    // public function testLogoutRemoveRefreshToken(): void
    // {
    //     $tokenVerificationRepository = $this->client->getContainer()->get(TokenVerificationRepository::class);
    //     $this->client->request(
    //         'POST',
    //         '/api/auth/login',
    //         server: [
    //             'CONTENT_TYPE' => 'application/json',
    //         ],
    //         content: json_encode([
    //             'username' => 'username',
    //             'password' => 'password',
    //         ], JSON_THROW_ON_ERROR)
    //     );

    //     $this->assertResponseIsSuccessful();
    //     $token = $this->testAndGetJsonResponse('token');
    //     $refreshToken = $this->testAndGetJsonResponse('refresh_token');

    //     $this->assertCount(1, $tokenVerificationRepository->findAll());

    //     $this->client->request(
    //         'POST',
    //         '/api/auth/logout',
    //         server: [
    //             'CONTENT_TYPE' => 'application/json',
    //             'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
    //         ],
    //         content: json_encode([
    //             'refreshToken' => $refreshToken,
    //         ], JSON_THROW_ON_ERROR)
    //     );

    //     $this->assertResponseIsSuccessful();
    //     $this->assertCount(0, $tokenVerificationRepository->findAll());
    // }
}
