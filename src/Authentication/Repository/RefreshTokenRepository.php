<?php

declare(strict_types=1);

namespace App\Authentication\Repository;

use App\Authentication\Entity\RefreshToken;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository implements RefreshTokenRepositoryInterface {
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }
    
    public function deleteRefreshToken(string $refreshToken): void
    {
        $this->createQueryBuilder('r')
            ->delete()
            ->where('r.refreshToken = :token')
            ->setParameter('token', $refreshToken)
            ->getQuery()
            ->execute()
        ;
    }

    /**
     * @param DateTimeInterface|null $datetime
     *
     * @return RefreshToken[]
     */
    public function findInvalid($datetime = null): array
    {
        $datetime = $datetime ?? new DateTime();

        return $this->createQueryBuilder('r')
            ->where('r.valid < :datetime')
            ->setParameter('datetime', $datetime)
            ->getQuery()
            ->getResult();
    }
}
