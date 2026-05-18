<?php

declare(strict_types=1);

namespace App\Tests\Authentication\EventListener;

use App\Authentication\Entity\User;
use App\Authentication\Repository\RefreshTokenRepository;
use App\Tests\Utils\Controller\CleanWebTestCase;
use DateTimeImmutable;
use Symfony\Component\BrowserKit\Cookie;

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
    public function testLogoutRemoveRefreshToken(): void
    {
        $refreshTokenRepository = $this->client->getContainer()->get(RefreshTokenRepository::class);
        $this->client->request(
            'POST',
            '/api/auth/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'username' => 'username',
                'password' => 'password',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();
        $token = $this->testAndGetJsonResponse('token');
        $loginResponse = $this->client->getResponse();                                                                                                          
        $loginCookies = $loginResponse->headers->getCookies();  
        $refreshCookie = $loginCookies[0];                                                                                                      
   
        $this->client->getCookieJar()->set(
            new Cookie(                                                                                     
                $refreshCookie->getName(),                                                                                                                                 
                $refreshCookie->getValue(),                                                                                                                                  
                null,                                                                                                                                                        
                $refreshCookie->getPath(),
            )
        );                                                                                                                                                              

        $allRefreshTokens = $refreshTokenRepository->findAll();
        $this->assertCount(1, $allRefreshTokens);
        $this->assertFalse($allRefreshTokens[0]->isRevoked());

        $this->client->request(
            'POST',
            '/api/auth/logout',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        $allRefreshTokens = $refreshTokenRepository->findAll();
        $this->assertCount(1, $allRefreshTokens);
        $this->assertTrue($allRefreshTokens[0]->isRevoked());
    }
}
