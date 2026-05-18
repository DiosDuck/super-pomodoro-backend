<?php

declare(strict_types=1);

namespace App\Authentication\Utils\DTO;

use App\Authentication\Entity\User;

class RotationResultDTO
{
    public function __construct(
        public User $user,
        public string $plaintext,
    ) {}
}
