<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Service;

use App\Authentication\Entity\RefreshToken;
use App\Authentication\Entity\User;
use App\Authentication\Repository\RefreshTokenRepository;
use App\Authentication\Service\RefreshTokenService;
use App\Authentication\Utils\Exception\RefreshTokenExpiredException;
use App\Authentication\Utils\Exception\RefreshTokenNotFoundException;
use App\Authentication\Utils\Exception\RefreshTokenReuseException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class RefreshTokenServiceTest extends TestCase
{
    private const TTL = 2592000;

    private RefreshTokenService $service;
    private RefreshTokenRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $entityManager;
    private PasswordHasherInterface&MockObject $hasher;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RefreshTokenRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->hasher = $this->createMock(PasswordHasherInterface::class);

        $this->service = new RefreshTokenService(
            $this->repository,
            $this->entityManager,
            $this->hasher,
            self::TTL,
        );
    }

    public function testIssueRefreshTokenPersistsAndReturnsPlaintext(): void
    {
        $user = new User();
        $user->setUsername('alice');

        $this->hasher->expects($this->once())
            ->method('hash')
            ->willReturn('hashed_verifier');

        $persistedToken = null;
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedToken): void {
                $persistedToken = $entity;
            });
        $this->entityManager->expects($this->once())->method('flush');

        $plaintext = $this->service->issueRefreshToken($user);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.[a-f0-9]{64}$/', $plaintext);
        $this->assertInstanceOf(RefreshToken::class, $persistedToken);
        $this->assertSame($user, $persistedToken->getUser());
        $this->assertSame('hashed_verifier', $persistedToken->getTokenHash());
        $this->assertNotNull($persistedToken->getIssuedAt());
        $this->assertNotNull($persistedToken->getExpiresAt());
        $this->assertGreaterThan(
            $persistedToken->getIssuedAt()->getTimestamp(),
            $persistedToken->getExpiresAt()->getTimestamp(),
        );
    }

    public function testRotateRefreshTokenHappyPath(): void
    {
        $user = new User();
        $user->setUsername('alice');

        $oldToken = $this->buildToken($user, 'selector', 'old_hash', revokedAt: null, expiresInHours: 1);

        $this->repository->expects($this->once())
            ->method('findOneBySelector')
            ->with('selector')
            ->willReturn($oldToken);

        $this->hasher->expects($this->once())
            ->method('verify')
            ->with('old_hash', 'verifier')
            ->willReturn(true);

        $this->hasher->expects($this->once())
            ->method('hash')
            ->willReturn('new_hash');

        $persistCount = 0;
        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistCount): void {
                $this->assertInstanceOf(RefreshToken::class, $entity);
                $persistCount++;
            });
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->rotateRefreshToken('selector.verifier');

        $this->assertSame($user, $result->user);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.[a-f0-9]{64}$/', $result->plaintext);
        $this->assertNotNull($oldToken->getRevokedAt());
        $this->assertInstanceOf(RefreshToken::class, $oldToken->getReplacedBy());
        $this->assertSame(2, $persistCount);
    }

    public function testRotateRefreshTokenRevokedThrowsReuseAndRevokesFamily(): void
    {
        $user = new User();
        $user->setUsername('alice');

        $revoked = $this->buildToken(
            $user,
            'selector',
            'hash',
            revokedAt: new \DateTimeImmutable('-1 hour'),
            expiresInHours: 1,
        );

        $this->repository->expects($this->once())
            ->method('findOneBySelector')
            ->with('selector')
            ->willReturn($revoked);

        $this->hasher->expects($this->once())
            ->method('verify')
            ->with('hash', 'verifier')
            ->willReturn(true);

        $this->repository->expects($this->once())
            ->method('revokeFamily')
            ->with($revoked, $this->isInstanceOf(\DateTimeImmutable::class));

        $this->entityManager->expects($this->once())->method('flush');

        $this->expectException(RefreshTokenReuseException::class);

        $this->service->rotateRefreshToken('selector.verifier');
    }

    public function testRotateRefreshTokenExpiredThrows(): void
    {
        $user = new User();
        $user->setUsername('alice');

        $expired = $this->buildToken($user, 'selector', 'hash', revokedAt: null, expiresInHours: -1);

        $this->repository->expects($this->once())
            ->method('findOneBySelector')
            ->with('selector')
            ->willReturn($expired);

        $this->hasher->expects($this->once())
            ->method('verify')
            ->willReturn(true);

        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(RefreshTokenExpiredException::class);

        $this->service->rotateRefreshToken('selector.verifier');
    }

    public function testRotateRefreshTokenMalformedThrowsNotFound(): void
    {
        $this->repository->expects($this->never())->method('findOneBySelector');

        $this->expectException(RefreshTokenNotFoundException::class);

        $this->service->rotateRefreshToken('no_delimiter_here');
    }

    public function testRotateRefreshTokenUnknownSelectorThrowsNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findOneBySelector')
            ->with('selector')
            ->willReturn(null);

        $this->expectException(RefreshTokenNotFoundException::class);

        $this->service->rotateRefreshToken('selector.verifier');
    }

    public function testRotateRefreshTokenWrongVerifierThrowsNotFound(): void
    {
        $token = $this->buildToken(new User(), 'selector', 'hash', revokedAt: null, expiresInHours: 1);

        $this->repository->expects($this->once())
            ->method('findOneBySelector')
            ->with('selector')
            ->willReturn($token);

        $this->hasher->expects($this->once())
            ->method('verify')
            ->willReturn(false);

        $this->expectException(RefreshTokenNotFoundException::class);

        $this->service->rotateRefreshToken('selector.verifier');
    }

    public function testRevokeRefreshTokenMarksRevoked(): void
    {
        $token = $this->buildToken(new User(), 'selector', 'hash', revokedAt: null, expiresInHours: 1);

        $this->repository->expects($this->once())
            ->method('findOneBySelector')
            ->with('selector')
            ->willReturn($token);

        $this->hasher->expects($this->once())
            ->method('verify')
            ->willReturn(true);

        $this->entityManager->expects($this->once())->method('persist')->with($token);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->revokeRefreshToken('selector.verifier');

        $this->assertNotNull($token->getRevokedAt());
    }

    public function testRevokeRefreshTokenUnknownIsNoOp(): void
    {
        $this->repository->expects($this->once())
            ->method('findOneBySelector')
            ->willReturn(null);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $this->service->revokeRefreshToken('selector.verifier');
    }

    public function testRevokeRefreshTokenAlreadyRevokedIsNoOp(): void
    {
        $token = $this->buildToken(
            new User(),
            'selector',
            'hash',
            revokedAt: new \DateTimeImmutable('-30 minutes'),
            expiresInHours: 1,
        );

        $this->repository->expects($this->once())
            ->method('findOneBySelector')
            ->willReturn($token);

        $this->hasher->expects($this->once())
            ->method('verify')
            ->willReturn(true);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $this->service->revokeRefreshToken('selector.verifier');
    }

    private function buildToken(
        User $user,
        string $selector,
        string $tokenHash,
        ?\DateTimeImmutable $revokedAt,
        int $expiresInHours,
    ): RefreshToken {
        $token = new RefreshToken();
        $token->setUser($user);
        $token->setSelector($selector);
        $token->setTokenHash($tokenHash);
        $token->setIssuedAt(new \DateTimeImmutable('-2 hours'));
        $token->setExpiresAt(new \DateTimeImmutable("{$expiresInHours} hours"));
        $token->setRevokedAt($revokedAt);

        return $token;
    }
}
