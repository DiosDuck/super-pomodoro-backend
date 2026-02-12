<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Controller;

use App\Authentication\Entity\TokenVerification;
use App\Authentication\Entity\User;
use App\Authentication\Utils\Enum\TokenTypeEnum;
use App\Tests\Utils\Controller\CleanWebTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class PasswordChangeControllerTest extends CleanWebTestCase
{
    public function testGetForgotPasswordBad(): void
    {
        $this->client->request('PUT', '/api/auth/password/forgot-password');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testGetForgotPasswordSuccessAndLimited(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('username');
        $user->setRoles(['ROLE_USER']);
        $user->setActivatedAt(new DateTimeImmutable());
        $user->setDisplayName('Test Me');
        $user->setIsActive(true);
        $user->setPassword('password');

        $this->saveUser($user);

        $this->client->request(
            'PUT',
            '/api/auth/password/forgot-password',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['username' => 'username'], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();

        $this->assertEmailSubjectContains($email, 'Password Reset');
        $this->assertEmailAddressContains($email, 'To', 'test@example.com');


        $this->client->request(
            'PUT',
            '/api/auth/password/forgot-password',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['username' => 'username'], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function testResetPasswordInvalidInput(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/password/reset-password',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['id' => 1], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testResetPasswordTokenNotFound(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/password/reset-password',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                ['id' => 1, 'token' => 'abcdef', 'newPassword' => 'password'], 
                JSON_THROW_ON_ERROR,
            )
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testResetPasswordTokenSuccess(): void
    {
        /** @var PasswordHasherInterface $tokenHasher */
        $tokenHasher = $this->client->getContainer()->get('security.password_hasher.token_hasher');

        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('username');
        $user->setRoles(['ROLE_USER']);
        $user->setActivatedAt(new DateTimeImmutable());
        $user->setDisplayName('Test Me');
        $user->setIsActive(true);
        $user->setPassword('password');

        $user = $this->saveUser($user);

        $token = new TokenVerification();
        $token->setUser($user);
        $token->setExpiresAt(new DateTimeImmutable("+5 minutes"));
        $token->setIsUsed(false);
        $token->setType(TokenTypeEnum::TOKEN_RESET_PASSWORD);
        $token->setToken(
            $tokenHasher->hash('abcdef')
        );

        $this->saveObjectToDatabase($token);

        $this->client->request(
            'POST',
            '/api/auth/password/reset-password',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                ['id' => 1, 'token' => 'abcdef', 'newPassword' => 'password'], 
                JSON_THROW_ON_ERROR,
            )
        );

        $this->assertResponseIsSuccessful();
    }
}
