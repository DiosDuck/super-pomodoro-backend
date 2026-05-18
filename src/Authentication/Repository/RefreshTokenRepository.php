<?php

declare(strict_types=1);

namespace App\Authentication\Repository;

use App\Authentication\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findOneBySelector(string $selector): ?RefreshToken
    {
        return $this->findOneBy(['selector' => $selector]);
    }

    /** Walks the replacedBy chain forward and revokes every node — reuse-detection lockout. */
    public function revokeFamily(RefreshToken $start, \DateTimeImmutable $now): void
    {
        $em = $this->getEntityManager();
        $node = $start;
        $seen = [];

        while ($node !== null) {
            if (isset($seen[$node->getId()])) {
                break;
            }
            $seen[$node->getId()] = true;

            if (!$node->isRevoked()) {
                $node->setRevokedAt($now);
                $em->persist($node);
            }
            $node = $node->getReplacedBy();
        }
    }

    public function purgeExpiredAndRevoked(\DateTimeImmutable $now, \DateTimeImmutable $revokedCutoff): int
    {
        return (int) $this->createQueryBuilder('r')
            ->delete()
            ->where('r.expiresAt < :now')
            ->orWhere('r.revokedAt IS NOT NULL AND r.revokedAt < :cutoff')
            ->setParameter('now', $now)
            ->setParameter('cutoff', $revokedCutoff)
            ->getQuery()
            ->execute();
    }
}
