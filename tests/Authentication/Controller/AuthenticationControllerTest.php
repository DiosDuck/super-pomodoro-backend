<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Controller;

use App\Authentication\Entity\TokenVerification;
use App\Authentication\Entity\User;
use App\Authentication\Utils\Enum\TokenTypeEnum;
use App\Tests\Utils\Controller\CleanWebTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class AuthenticationControllerTest extends CleanWebTestCase {

    public function testRegisterBadRequest(): void
    {
        $this->client->request(
            'PUT',
            '/api/auth/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'username' => 'a',
                    'password' => 'password',
                    'email' => 'user@email.com',
                    'displayName' => 'User',
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRegisterUsernameExists(): void
    {
        $user = new User();
        $user->setEmail('user@email.com');
        $user->setUsername('username');
        $user->setRoles(['ROLE_USER']);
        $user->setActivatedAt(new DateTimeImmutable());
        $user->setDisplayName('Username');
        $user->setIsActive(true);
        $user->setPassword('password');

        $this->saveUser($user);

        $this->client->request(
            'PUT',
            '/api/auth/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'username' => 'username',
                    'password' => 'password',
                    'email' => 'user@email.com',
                    'displayName' => 'User',
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRegisterSuccessAndLimitRateReached(): void
    {
        $this->client->request(
            'PUT',
            '/api/auth/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'username' => 'username',
                    'password' => 'password',
                    'email' => 'user@email.com',
                    'displayName' => 'User',
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertEmailSubjectContains($email, 'Registering Account');
        $this->assertEmailAddressContains($email, 'To', 'user@email.com');


        $this->client->request(
            'PUT',
            '/api/auth/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'username' => 'username2',
                    'password' => 'password',
                    'email' => 'user@email.com',
                    'displayName' => 'User',
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function testVerifyEmailBadRequest(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register/verify-email',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'token' => '',
                    'id' => 1,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testVerifyEmailUserNotFound(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register/verify-email',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'token' => 'abcdef',
                    'id' => 1,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testVerifyEmailTokenNotFound(): void
    {
        $user = new User();
        $user->setEmail('user@email.com');
        $user->setUsername('username');
        $user->setRoles(['ROLE_USER']);
        $user->setDisplayName('Username');
        $user->setIsActive(false);
        $user->setPassword('password');

        $user = $this->saveUser($user);

        $this->client->request(
            'POST',
            '/api/auth/register/verify-email',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'token' => 'abcdef',
                    'id' => $user->getId(),
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testVerifyEmailSuccess(): void
    {
        /** @var PasswordHasherInterface $tokenHasher */
        $tokenHasher = $this->client->getContainer()->get('security.password_hasher.token_hasher');

        $user = new User();
        $user->setEmail('user@email.com');
        $user->setUsername('username');
        $user->setRoles(['ROLE_USER']);
        $user->setDisplayName('Username');
        $user->setIsActive(false);
        $user->setPassword('password');

        $user = $this->saveUser($user);

        $token = new TokenVerification();
        $token->setUser($user);
        $token->setExpiresAt(new DateTimeImmutable("+5 minutes"));
        $token->setIsUsed(false);
        $token->setType(TokenTypeEnum::TOKEN_EMAIL_VERIFICATION);
        $token->setToken(
            $tokenHasher->hash('abcdef')
        );

        $this->saveObjectToDatabase($token);

        $this->client->request(
            'POST',
            '/api/auth/register/verify-email',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'token' => 'abcdef',
                    'id' => $user->getId(),
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $this->assertResponseIsSuccessful();
        $user = $this->getUser('username');

        $this->assertTrue($user->isActive());
    }
}
