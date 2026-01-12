<?php

declare(strict_types=1);

namespace App\Authentication\Service;

use App\Authentication\DTO\CreatedTokenDTO;
use App\Authentication\DTO\RegisterUserDTO;
use App\Authentication\Entity\TokenVerification;
use App\Authentication\Entity\User;
use App\Authentication\Enum\TokenTypeEnum;
use App\Authentication\Exception\InvalidPasswordException;
use App\Authentication\Exception\InvalidRegisterDataException;
use App\Authentication\Exception\InvalidTokenException;
use App\Authentication\Exception\UserNotFoundException;
use App\Authentication\Repository\TokenVerificationRepository;
use App\Authentication\Repository\UserRepository;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class AuthenticationService {
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenVerificationRepository $tokenVerificationRepository,
        #[Target('token_hasher')]
        private readonly PasswordHasherInterface $tokenHasher,
    ) { }

    /**
     * @throws InvalidRegisterDataException
     */
    public function getUserFromRegisterData(RegisterUserDTO $registerUser): User
    {
        if (
            !$registerUser->isValid() ||
            $this->userRepository->findOneBy(['username' => $registerUser->username])
        ) {
            throw new InvalidRegisterDataException();
        }

        $user = new User();
        
        $user->setDisplayName($registerUser->displayName)
            ->setEmail($registerUser->email)
            ->setUsername($registerUser->username)
            ->setRoles(['ROLE_USER'])
            ->setIsActive(false)
            ->setActivatedAt(null)
            ->setLastLoggedIn(null)
        ;

        $hashedPassword = $this->passwordHasher->hashPassword($user, $registerUser->password);
        $user->setPassword($hashedPassword);

        return $user;
    }

    public function updatePasswordForUser(User $user, string $newPassword): User
    {
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        return $user;
    }

    /**
     * @throws UserNotFoundException
     */
    public function getUserByUsername(string $username): User 
    {
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    /**
     * @throws InvalidTokenException
     */
    public function getValidTokenForUser(int $userId, TokenTypeEnum $type, string $token): TokenVerification
    {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new InvalidTokenException('user not found');
        }

        $tokenVerification = $this->tokenVerificationRepository->findValidTokenByUserAndType($user, $type);
        if (
            !$tokenVerification
            || !$this->tokenHasher->verify($tokenVerification->getToken(), $token)
         ) {
            throw new InvalidTokenException('token invalid');
        }

        return $tokenVerification;
    }

    public function createToken(TokenTypeEnum $tokenType, User $user): CreatedTokenDTO
    {
        $token = bin2hex(random_bytes(32));
        $tokenVerification = new TokenVerification();

        $tokenVerification
            ->setUser($user)
            ->setExpiresAt(new DateTimeImmutable("+24 hours"))
            ->setType($tokenType)
            ->setIsUsed(false)
            ->setToken($this->tokenHasher->hash($token))
        ;

        return new CreatedTokenDTO(
            tokenVerification: $tokenVerification,
            unhashedToken: $token,
        );
    }

    /**
     * @throws InvalidPasswordException
     */
    public function changePassword(User $user, string $oldPassword, string $newPassword): User
    {
        if (!$this->passwordHasher->isPasswordValid($user, $oldPassword)) {
            throw new InvalidPasswordException();
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        return $user;
    }
}
