<?php

declare(strict_types=1);

namespace App\Authentication\Utils\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'Register User Schema',
    description: 'Register User Schema for register into DB',
)]
class RegisterUserDTO {
    #[Assert\Length(min: 6, max: 20)]
    #[OA\Property(example: 'john_smith')]
    public string $username;
    #[Assert\Length(min: 6, max: 20)]
    #[OA\Property(example: 'password')]
    public string $password;
    #[Assert\Email]
    #[OA\Property(example: 'john.smith@email.com')]
    public string $email;
    #[OA\Property(example: 'John Smith')]
    public string $displayName;
}
