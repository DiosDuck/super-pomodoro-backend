<?php

declare(strict_types=1);

namespace App\Pomodoro\DTO;

use OpenApi\Attributes as OA;


#[OA\Schema(
    title: 'Session History Daily',
    description: 'Get session history for the user by day',
)]
class SessionHistoryDailyDTO {
    public function __construct(
        #[OA\Property(description: 'Total amount in seconds', example: 2400)]
        public int $workTimeTotal,
        #[OA\Property(description: 'Number of sessions done', example: 3)]
        public int $sessionAmount,
        #[OA\Property(description: 'Timestamp (in miliseconds)', example: 1767484800000)]
        public int $timestamp,
    ) {}
}
