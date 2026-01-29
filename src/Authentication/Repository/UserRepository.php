<?php

namespace App\Authentication\Repository;

use App\Authentication\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }
//eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3Njk2ODk4ODMsImV4cCI6MTc2OTY5MDc4MywianRpIjoiMTFlN2U2MzM2NzYxMmRhYzIwYTJjZWQ5NzhhYjg4N2EiLCJyb2xlcyI6WyJST0xFX1VTRVIiLCJST0xFX0FETUlOIl0sInVzZXJuYW1lIjoidXNlcm5hbWUifQ.SeCgLl2WupYyFzxFDgHzdmsfMVZMDgsELAFgHAtQLe-Az2dOKzWPDDRd-b4hh7-P0n0aAh0STdwL_rIxIH0FcmDgPi8MSNKIkOm6BRya3b_KZR9RbWvJNk44xelFinfFlpgJSbRN0zyGrmxDZPL0EMyDummjnvQJTJ0Fo25EGhyNAfa4aKmotExhops7veFpgRGK-Vsygy0U5yQZSqQE0dyEcOd8c3I0Vu-dK5cmwjnLVsPkCUfLTTrXP87OKvY63JJCKB5xeoy1njfatwyWIMVtB_gq370LW3xjrBJVjpFukjOT1KpVgHGxkK8OcZkaOesH_YEjXuzTvoRQW2YiAw
    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function deleteAllUsersIn(array $ids): int
    {
        return $this->createQueryBuilder('u')
            ->delete()
            ->where('u.id in (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute()
        ;
    }
}
