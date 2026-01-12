<?php

declare(strict_types=1);

namespace App\Authentication\Repository;

use App\Authentication\Entity\TokenVerification;
use App\Authentication\Entity\User;
use App\Authentication\Enum\TokenTypeEnum;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Token>
 */
class TokenVerificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TokenVerification::class);
    }

    /**
     * @return TokenVerification[]
     */
    public function getAllUnusedExpiredValidationToken(): array
    {
        return  $this->createQueryBuilder('t')
            ->andWhere('t.expiresAt <= :expiresAt')
            ->setParameter('expiresAt', new DateTimeImmutable())
            ->andWhere('t.type = :type')
            ->setParameter('type', TokenTypeEnum::TOKEN_EMAIL_VERIFICATION)
            ->andWhere('t.isUsed = :isUsed')
            ->setParameter('isUsed', false)
            ->getQuery()
            ->getResult()
        ;
    }

    public function deleteAllExpiredOrUsedTokens(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiresAt <= :expiresAt')
            ->setParameter('expiresAt', new DateTimeImmutable())
            ->orWhere('t.isUsed = :isUsed')
            ->setParameter('isUsed', true)
            ->getQuery()
            ->execute()
        ;
    }

    public function findValidTokenByUserAndType(User $user, TokenTypeEnum $type): ?TokenVerification
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.expiresAt > :expiresAt')
            ->setParameter('expiresAt', new DateTimeImmutable())
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->andWhere('t.type = :type')
            ->setParameter('type', $type)
            ->andWhere('t.isUsed = :isUsed')
            ->setParameter('isUsed', false)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
