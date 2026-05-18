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

        $token = $this->createRefreshTokenRow(
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

        // Expiry alone must not flip revoked_at — only reuse-detection does that.
        $em = $this->client->getContainer()->get('doctrine')->getManager();
        $em->clear();
        $refreshed = $em->getRepository(RefreshToken::class)->find($token->getId());
        $this->assertNull($refreshed->getRevokedAt());
    }

    public function testReusedRefreshTokenRevokesEntireFamily(): void
    {
        $user = $this->createActiveUser();

        // RT2 — the legitimate user's CURRENT (active) refresh token.
        $rt2Selector = 'sel-rt2-' . bin2hex(random_bytes(4));
        $rt2Verifier = 'ver-rt2-' . bin2hex(random_bytes(8));
        $rt2 = $this->createRefreshTokenRow(
            $user,
            $rt2Selector,
            $rt2Verifier,
            new \DateTimeImmutable('+1 day'),
        );

        // RT1 — already revoked, replaced by RT2. Replaying it is the attacker scenario.
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

        // RT2 must now also be revoked: the family-walk locks the legitimate user out too.
        $em = $this->client->getContainer()->get('doctrine')->getManager();
        $em->clear();
        $refreshedRt2 = $em->getRepository(RefreshToken::class)->find($rt2->getId());
        $this->assertNotNull($refreshedRt2->getRevokedAt());
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
