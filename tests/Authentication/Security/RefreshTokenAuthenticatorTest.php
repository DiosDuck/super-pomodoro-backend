<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Security;

use App\Authentication\Entity\RefreshToken;
use App\Authentication\Entity\User;
use App\Authentication\Security\RefreshTokenAuthenticator;
use App\Authentication\Utils\Factory\RefreshTokenCookieFactory;
use App\Tests\Utils\Controller\CleanWebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

class RefreshTokenAuthenticatorTest extends CleanWebTestCase
{
    public function testMissingRefreshTokenReturnsUnauthorized(): void
    {
        $this->client->request(
            'POST',
            RefreshTokenAuthenticator::REFRESH_ENDPOINT,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertEquals('Invalid refresh token.', $this->testAndGetJsonResponse('message'));
        $this->assertResponseClearsRefreshCookie();
    }

    public function testUnknownRefreshTokenReturnsUnauthorized(): void
    {
        $this->createActiveUser();

        $this->setRefreshCookie('unknown-selector', 'unknown-verifier');
        $this->client->request('POST', RefreshTokenAuthenticator::REFRESH_ENDPOINT);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertEquals('Invalid refresh token.', $this->testAndGetJsonResponse('message'));
        $this->assertResponseClearsRefreshCookie();
    }

    public function testExpiredRefreshTokenReturnsUnauthorized(): void
    {
        $user = $this->createActiveUser();
        $selector = 'sel-' . bin2hex(random_bytes(8));
        $verifier = 'ver-' . bin2hex(random_bytes(16));

        $this->createRefreshTokenRow(
            $user,
            $selector,
            $verifier,
            new \DateTimeImmutable('-1 minute'),
        );

        $this->setRefreshCookie($selector, $verifier);
        $this->client->request('POST', RefreshTokenAuthenticator::REFRESH_ENDPOINT);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertEquals('Invalid refresh token.', $this->testAndGetJsonResponse('message'));
        $this->assertResponseClearsRefreshCookie();
    }

    public function testReusedRefreshTokenRevokesEntireFamily(): void
    {
        $user = $this->createActiveUser();

        $rt2Selector = 'sel-rt2-' . bin2hex(random_bytes(4));
        $rt2Verifier = 'ver-rt2-' . bin2hex(random_bytes(8));
        $rt2 = $this->createRefreshTokenRow(
            $user,
            $rt2Selector,
            $rt2Verifier,
            new \DateTimeImmutable('+1 day'),
        );

        $rt1Selector = 'sel-rt1-' . bin2hex(random_bytes(4));
        $rt1Verifier = 'ver-rt1-' . bin2hex(random_bytes(8));
        $this->createRefreshTokenRow(
            $user,
            $rt1Selector,
            $rt1Verifier,
            new \DateTimeImmutable('+1 day'),
            new \DateTimeImmutable('-1 hour'),
            $rt2,
        );

        $this->setRefreshCookie($rt1Selector, $rt1Verifier);
        $this->client->request('POST', RefreshTokenAuthenticator::REFRESH_ENDPOINT);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertEquals('Invalid refresh token.', $this->testAndGetJsonResponse('message'));
        $this->assertResponseClearsRefreshCookie();

        $em = $this->client->getContainer()->get('doctrine')->getManager();
        $refreshedRt2 = $em->getRepository(RefreshToken::class)->find($rt2->getId());
        $this->assertNotNull($refreshedRt2->getRevokedAt());
    }

    public function testRefreshSuccessAndRateLimitReached(): void
    {
        $user = $this->createActiveUser();
        $selector = 'sel-' . bin2hex(random_bytes(8));
        $verifier = 'ver-' . bin2hex(random_bytes(16));

        $oldToken = $this->createRefreshTokenRow(
            $user,
            $selector,
            $verifier,
            new \DateTimeImmutable('+1 day'),
        );

        $this->setRefreshCookie($selector, $verifier);
        $this->client->request('POST', RefreshTokenAuthenticator::REFRESH_ENDPOINT);

        $this->assertResponseIsSuccessful();
        $jwt = $this->testAndGetJsonResponse('token');
        $this->assertNotEmpty($jwt);

        $cookies = $this->client->getResponse()->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertSame(RefreshTokenCookieFactory::COOKIE_NAME, $cookies[0]->getName());
        $this->assertFalse($cookies[0]->isCleared());
        $this->assertNotSame($selector . '.' . $verifier, $cookies[0]->getValue());

        $em = $this->client->getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(RefreshToken::class);

        $refreshedOld = $repo->find($oldToken->getId());
        $this->assertNotNull($refreshedOld->getRevokedAt());
        $this->assertNotNull($refreshedOld->getReplacedBy());

        $newToken = $refreshedOld->getReplacedBy();
        $this->assertNull($newToken->getRevokedAt());
        $this->assertGreaterThan(new \DateTimeImmutable(), $newToken->getExpiresAt());

        $newParts = explode('.', $cookies[0]->getValue(), 2);
        $this->setRefreshCookie($newParts[0], $newParts[1]);

        $this->client->request('POST', RefreshTokenAuthenticator::REFRESH_ENDPOINT);
        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertEquals('Too many requests.', $this->testAndGetJsonResponse('message'));
    }

    private function createActiveUser(): User
    {
        $user = new User();
        $user->setUsername('username');
        $user->setPassword('password');
        $user->setEmail('user@email.com');
        $user->setDisplayName('Username');
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);
        $user->setActivatedAt(new \DateTimeImmutable());

        return $this->saveUser($user);
    }

    private function createRefreshTokenRow(
        User $user,
        string $selector,
        string $verifier,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $revokedAt = null,
        ?RefreshToken $replacedBy = null,
    ): RefreshToken {
        $hasher = $this->client->getContainer()->get('security.password_hasher.refresh_token_hasher');

        $token = new RefreshToken();
        $token->setUser($user);
        $token->setSelector($selector);
        $token->setTokenHash($hasher->hash($verifier));
        $token->setIssuedAt(new \DateTimeImmutable('-1 hour'));
        $token->setExpiresAt($expiresAt);
        $token->setRevokedAt($revokedAt);
        $token->setReplacedBy($replacedBy);

        $this->saveObjectToDatabase($token);
        return $token;
    }

    private function setRefreshCookie(string $selector, string $verifier): void
    {
        $this->client->getCookieJar()->set(
            new Cookie(
                RefreshTokenCookieFactory::COOKIE_NAME,
                $selector . '.' . $verifier,
                null,
                RefreshTokenCookieFactory::PATH,
            )
        );
    }

    private function assertResponseClearsRefreshCookie(): void
    {
        $cookies = $this->client->getResponse()->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertSame(RefreshTokenCookieFactory::COOKIE_NAME, $cookies[0]->getName());
        $this->assertTrue($cookies[0]->isCleared());
    }
}
