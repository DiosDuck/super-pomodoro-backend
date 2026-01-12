<?php

declare(strict_types=1);

namespace App\Authentication\DTO;

use App\Authentication\Entity\User;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'User Schema',
    description: 'User Schema for basic info',
)]
class UserDTO {
    public function __construct(
        #[OA\Property(type: 'string', example: 'John Smith')]
        public string $displayName,
        #[OA\Property(type: 'string', example: 'john@email.com')]
        public string $email,
        #[OA\Property(type: 'string', example: 'username')]
        public string $username,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string', example: 'ROLE_USER'))]
        public array $roles,
        #[OA\Property(type: 'number', example: '1767687728')]
        public int $activatedAtTimeStamp,
    ) { }

    public static function fromUser(User $user): self
    {
        return new UserDTO(
            displayName: $user->getDisplayName(),
            email: $user->getEmail(),
            username: $user->getUsername(),
            roles: $user->getRoles(),
            activatedAtTimeStamp: $user->getActivatedAt()->getTimestamp(),
        );
    }
}
