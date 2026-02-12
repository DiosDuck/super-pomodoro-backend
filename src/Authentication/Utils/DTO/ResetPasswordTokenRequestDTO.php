<?php

declare(strict_types=1);

namespace App\Authentication\Utils\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'Reset Password Token Request',
    description: 'Verification token request body used to validate the tokens',
)]
class ResetPasswordTokenRequestDTO extends VerificationTokenRequestDTO
{
    #[Assert\NotBlank]
    #[OA\Property(example: 'new-password')]
    public string $newPassword;
}
