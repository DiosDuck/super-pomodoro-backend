<?php

declare(strict_types=1);

namespace App\Authentication\Utils\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'Change Password Request',
    description: 'POST Body request for changing password'
)]
class ChangePasswordRequestDTO {
    #[Assert\NotBlank]
    #[OA\Property(example: 'password')]
    public string $password;
    #[Assert\Length(min: 6, max: 20)]
    #[OA\Property(example: 'newPassword')]
    public string $newPassword;
}
