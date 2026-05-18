<?php

declare(strict_types=1);

namespace App\Authentication\Service;

use App\Authentication\Entity\RefreshToken;
use App\Authentication\Entity\User;
use App\Authentication\Repository\RefreshTokenRepository;
use App\Authentication\Utils\DTO\RefreshTokenStringDTO;
use App\Authentication\Utils\DTO\RotationResultDTO;
use App\Authentication\Utils\Exception\RefreshTokenExpiredException;
use App\Authentication\Utils\Exception\RefreshTokenNotFoundException;
use App\Authentication\Utils\Exception\RefreshTokenReuseException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class RefreshTokenService
{
    private const SELECTOR_BYTES = 16;
    private const VERIFIER_BYTES = 32;
    private const DELIMITER = '.';

    public function __construct(
        private readonly RefreshTokenRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        #[Target('refresh_token_hasher')]
        private readonly PasswordHasherInterface $hasher,
        private readonly int $refreshTokenTtl,
    ) {}

    public function issueRefreshToken(User $user): string
    {
        $now = new \DateTimeImmutable();
        $parsed = new RefreshTokenStringDTO($this->generateRandomPlaintext(), self::DELIMITER);

        $this->createTokenRow($user, $parsed, $now);
        $this->entityManager->flush();

        return $parsed->getPlaintext();
    }

    public function rotateRefreshToken(string $presentedRefreshToken): RotationResultDTO
    {
        $now = new \DateTimeImmutable();
        $oldToken = $this->loadByPresentedRefreshToken($presentedRefreshToken);

        $this->detectReuse($oldToken, $now);
        $this->guardExpiry($oldToken, $now);

        $user = $oldToken->getUser();

        $newParsed = new RefreshTokenStringDTO($this->generateRandomPlaintext(), self::DELIMITER);
        $newToken = $this->createTokenRow($user, $newParsed, $now);

        $oldToken->setRevokedAt($now);
        $oldToken->setReplacedBy($newToken);
        $this->entityManager->persist($oldToken);

        $this->entityManager->flush();

        return new RotationResultDTO($user, $newParsed->getPlaintext());
    }

    public function revokeRefreshToken(string $presentedRefreshToken): void
    {
        try {
            $token = $this->loadByPresentedRefreshToken($presentedRefreshToken);
        } catch (RefreshTokenNotFoundException) {
            return;
        }

        if ($token->isRevoked()) {
            return;
        }

        $token->setRevokedAt(new \DateTimeImmutable());
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    private function generateRandomPlaintext(): string
    {
        return bin2hex(random_bytes(self::SELECTOR_BYTES))
            . self::DELIMITER
            . bin2hex(random_bytes(self::VERIFIER_BYTES));
    }

    private function createTokenRow(User $user, RefreshTokenStringDTO $parsed, \DateTimeImmutable $now): RefreshToken
    {
        $token = new RefreshToken();
        $token->setUser($user);
        $token->setSelector($parsed->getSelector());
        $token->setTokenHash($this->hasher->hash($parsed->getVerifier()));
        $token->setIssuedAt($now);
        $token->setExpiresAt($now->modify("+{$this->refreshTokenTtl} seconds"));

        $this->entityManager->persist($token);

        return $token;
    }

    /**
     * @throws RefreshTokenNotFoundException
     */
    private function loadByPresentedRefreshToken(string $presentedRefreshToken): RefreshToken
    {
        $parsed = new RefreshTokenStringDTO($presentedRefreshToken, self::DELIMITER);
        if ($parsed->getSelector() === '' || $parsed->getVerifier() === '') {
            throw new RefreshTokenNotFoundException('Malformed refresh token.');
        }

        $token = $this->repository->findOneBySelector($parsed->getSelector());
        if ($token === null || !$this->hasher->verify($token->getTokenHash() ?? '', $parsed->getVerifier())) {
            throw new RefreshTokenNotFoundException('Unknown refresh token.');
        }

        return $token;
    }

    /**
     * @throws RefreshTokenReuseException
     */
    private function detectReuse(RefreshToken $token, \DateTimeImmutable $now): void
    {
        if (!$token->isRevoked()) {
            return;
        }
        $this->repository->revokeFamily($token, $now);
        $this->entityManager->flush();
        throw new RefreshTokenReuseException('Refresh token reuse detected.');
    }

    /**
     * @throws RefreshTokenExpiredException
     */
    private function guardExpiry(RefreshToken $token, \DateTimeImmutable $now): void
    {
        if ($token->isExpired($now)) {
            throw new RefreshTokenExpiredException('Refresh token expired.');
        }
    }
}
