<?php

declare(strict_types=1);

namespace App\Pomodoro\Service;

use App\Authentication\Entity\User;
use App\Pomodoro\Repository\SessionSavedRepository;
use App\Pomodoro\Repository\SettingsRepository;
use DateInterval;
use DateTimeImmutable;

class WorkSessionService {
    public function __construct(
        private readonly SessionSavedRepository $sessionSavedRepository,
        private readonly SettingsRepository $settingsRepository,
    ) { }

    public function isNewWorkSessionValid(User $user, int $workTime): bool
    {
        $settings = $this->settingsRepository->findOneByUser($user);
        if (null === $settings || $settings->getWorkTime() !== $workTime) {
            return false;
        }

        $sessionSaved = $this->sessionSavedRepository->findLastWorkSession($user);
        if (null === $sessionSaved) {
            return true;
        }

        $now = new DateTimeImmutable();
        $diff = $now->getTimestamp() - $sessionSaved->getCreatedAt()->getTimestamp();

        return $diff >= $workTime;
    }

    public function getHistoryForAWeek(User $user, int $lastDayTimestamp): array
    {
        $lastDay = new DateTimeImmutable(
            sprintf('@%d', intval($lastDayTimestamp / 1000))
        );

        $dayInterval = DateInterval::createFromDateString('1 day');
        $list = [];

        for ($i = 0; $i < 7; $i++) {
            array_unshift(
                $list,
                $this->sessionSavedRepository->getSessionHistoryForADay(
                    $user, $lastDay
                ),
            );

            $lastDay = $lastDay->sub($dayInterval);
        }

        return $list;
    }
}