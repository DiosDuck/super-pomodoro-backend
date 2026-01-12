<?php

declare(strict_types=1);

namespace App\Authentication\DTO;

use App\Authentication\Entity\TokenVerification;

class CreatedTokenDTO {
    public function __construct(
        public TokenVerification $tokenVerification,
        public string $unhashedToken,
    ) { }
}