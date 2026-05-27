<?php

declare(strict_types=1);

namespace App\Pomodoro\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'Save Work Session Request',
    description: 'Payload for saving a completed work session',
)]
class SaveWorkSessionRequest
{
    public function __construct(
        #[Assert\Positive]
        #[OA\Property(description: 'Work session length in seconds', example: 1500)]
        public readonly int $workTime,
    ) {}
}
