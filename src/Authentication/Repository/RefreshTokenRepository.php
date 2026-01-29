<?php

declare(strict_types=1);

namespace App\Authentication\Repository;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository as BaseRefreshTokenRepository;

class RefreshTokenRepository extends BaseRefreshTokenRepository {
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
}
