<?php

declare(strict_types=1);

namespace App\Pomodoro\Repository;

use App\Authentication\Entity\User;
use App\Pomodoro\DTO\SessionHistoryDailyDTO;
use App\Pomodoro\Entity\SessionSaved;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends SessionSavedRepository<SessionSaved>
 */
class SessionSavedRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        return parent::__construct($registry, SessionSaved::class);
    }

    public function findLastWorkSession(User $user): ?SessionSaved
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function deleteAllOldSessions(string $oldString = '-8 days'): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.createdAt <= :createdAt')
            ->setParameter('createdAt', new DateTimeImmutable($oldString))
            ->getQuery()
            ->execute()
        ;
    }

    public function getSessionHistoryForADay(User $user, DateTimeImmutable $startDateTime): SessionHistoryDailyDTO
    {
        $row = $this->createQueryBuilder('s')
            ->select('SUM(s.workTime) as workTimeTotal, COUNT(s) as sessionAmount')
            ->andWhere('s.createdAt >= :startCreatedAt')
            ->setParameter('startCreatedAt', $startDateTime)
            ->andWhere('s.createdAt < :endCreatedAt')
            ->setParameter('endCreatedAt', $startDateTime->modify('+1 day'))
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult()
        ;

        return new SessionHistoryDailyDTO(
            workTimeTotal: (int) $row[0]['workTimeTotal'] ?? 0,
            sessionAmount: (int) $row[0]['sessionAmount'] ?? 0,
            timestamp: $startDateTime->getTimestamp() * 1000,
        );
    }
}
