<?php

declare(strict_types=1);

namespace App\Authentication\Utils\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'Verification Token Request',
    description: 'Verification token request body used to validate the tokens',
)]
class VerificationTokenRequestDTO {
    #[Assert\NotBlank]
    #[OA\Property(example: 'abcdefghijklmnopqrstuvwxyz')]
    public string $token;
    
    #[Assert\NotBlank]
    #[OA\Property(example: 2)]
    public int $id;
}
