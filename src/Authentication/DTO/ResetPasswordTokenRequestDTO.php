<?php

declare(strict_types=1);

namespace App\Authentication\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Reset Password Token Request',
    description: 'Verification token request body used to validate the tokens',
)]
class ResetPasswordTokenRequestDTO extends VerificationTokenRequestDTO
{
    #[OA\Property(example: 'new-password')]
    public string $newPassword;
}
