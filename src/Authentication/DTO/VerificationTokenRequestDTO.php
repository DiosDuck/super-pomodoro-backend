<?php

declare(strict_types=1);

namespace App\Authentication\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Verification Token Request',
    description: 'Verification token request body used to validate the tokens',
)]
class VerificationTokenRequestDTO {
    #[OA\Property(example: 'abcdefghijklmnopqrstuvwxyz')]
    public string $token;
    #[OA\Property(example: 2)]
    public int $id;
}
