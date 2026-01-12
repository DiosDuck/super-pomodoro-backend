<?php

namespace App\Authentication\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Ignore;

#[OA\Schema(
    title: 'Register User Schema',
    description: 'Register User Schema for register into DB',
)]
class RegisterUserDTO {
    #[OA\Property(example: 'john_smith')]
    public string $username;
    #[OA\Property(example: 'password')]
    public string $password;
    #[OA\Property(example: 'john.smith@email.com')]
    public string $email;
    #[OA\Property(example: 'John Smith')]
    public string $displayName;

    #[Ignore]
    public function isValid(): bool
    {
        return strlen($this->username) >=6 && strlen($this->username) <= 20
            && strlen($this->password) >= 6 && strlen($this->password) <= 20
            && filter_var($this->email, FILTER_VALIDATE_EMAIL)
        ;
    }
}
