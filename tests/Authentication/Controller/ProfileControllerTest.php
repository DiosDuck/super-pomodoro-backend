<?php

declare(strict_types=1);

use App\Authentication\Entity\User;
use App\Tests\Utils\Controller\CleanWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProfileControllerTest extends CleanWebTestCase
{
    public function testGetProfileUserNotFound(): void
    {
        $this->client->request('GET', '/api/profile');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetProfileUserFound(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('username');
        $user->setRoles(['ROLE_USER']);
        $user->setActivatedAt(new DateTimeImmutable());
        $user->setDisplayName('Test Me');
        $user->setIsActive(true);
        $user->setPassword('password');

        $user = $this->saveUser($user);

        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'GET',
            '/api/profile',
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)]
        );

        $this->assertResponseIsSuccessful();
        $this->assertEquals($user->getDisplayName(), $this->testAndGetJsonResponse('displayName'));
    }

    public function testDeleteAccountNotLoggedIn(): void
    {
        $this->client->request('DELETE', '/api/profile');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testDeleteAccountWrongPassword(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('username');
        $user->setRoles(['ROLE_USER']);
        $user->setActivatedAt(new DateTimeImmutable());
        $user->setDisplayName('Test Me');
        $user->setIsActive(true);
        $user->setPassword('password');

        $user = $this->saveUser($user);

        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'DELETE',
            '/api/profile',
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
            content: json_encode(['password' => 'wrong_password'], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteAccountSuccess(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('username');
        $user->setRoles(['ROLE_USER']);
        $user->setActivatedAt(new DateTimeImmutable());
        $user->setDisplayName('Test Me');
        $user->setIsActive(true);
        $user->setPassword('password');

        $user = $this->saveUser($user);

        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'DELETE',
            '/api/profile',
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
            content: json_encode(['password' => 'password'], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();
    }
}
