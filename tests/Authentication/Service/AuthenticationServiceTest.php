<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Service;

use App\Authentication\Entity\TokenVerification;
use App\Authentication\Entity\User;
use App\Authentication\Repository\TokenVerificationRepository;
use App\Authentication\Repository\UserRepository;
use App\Authentication\Service\AuthenticationService;
use App\Authentication\Utils\DTO\RegisterUserDTO;
use App\Authentication\Utils\Enum\TokenTypeEnum;
use App\Authentication\Utils\Exception\InvalidPasswordException;
use App\Authentication\Utils\Exception\InvalidTokenException;
use App\Authentication\Utils\Exception\UserFoundException;
use App\Authentication\Utils\Exception\UserNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class AuthenticationServiceTest extends TestCase
{
    private AuthenticationService $authenticationService;
    private UserRepository&MockObject $userRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private TokenVerificationRepository&MockObject $tokenVerificationRepository;
    private PasswordHasherInterface&MockObject $tokenHasher;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->tokenVerificationRepository = $this->createMock(TokenVerificationRepository::class);
        $this->tokenHasher = $this->createMock(PasswordHasherInterface::class);

        $this->authenticationService = new AuthenticationService(
            $this->userRepository,
            $this->passwordHasher,
            $this->tokenVerificationRepository,
            $this->tokenHasher,
        );
    }

    public function testGetUserFromRegisterDataUsernameUsed(): void
    {
        $this->expectException(UserFoundException::class);

        $user = $this->createMock(User::class);
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'username_used'])
            ->willReturn($user);

        $registerUserDTO = new RegisterUserDTO();
        $registerUserDTO->username = 'username_used';
        $registerUserDTO->password = 'password';
        $registerUserDTO->email = 'user@email.com';
        $registerUserDTO->displayName = 'User';

        $this->authenticationService->getUserFromRegisterData($registerUserDTO);
    }

    public function testGetUserFromRegisterDataSuccess(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'username'])
            ->willReturn(null);
        
        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');
        
        $registerUserDTO = new RegisterUserDTO();
        $registerUserDTO->username = 'username';
        $registerUserDTO->password = 'password';
        $registerUserDTO->email = 'user@email.com';
        $registerUserDTO->displayName = 'User';

        $user = $this->authenticationService->getUserFromRegisterData($registerUserDTO);
        $this->assertEquals('username', $user->getUsername());
        $this->assertEquals('hashed_password', $user->getPassword());
        $this->assertEquals('user@email.com', $user->getEmail());
        $this->assertEquals('User', $user->getDisplayName());
        $this->assertFalse($user->isActive());
        $this->assertNull($user->getActivatedAt());
        $this->assertNull($user->getLastLoggedIn());
    }

    public function testGetUserByUsernameNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'username_missing'])
            ->willReturn(null);
        
        $this->authenticationService->getUserByUsername('username_missing');
    }

    public function testGetUserByUsernameSuccess(): void
    {
        $expected = $this->createMock(User::class);
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'username'])
            ->willReturn($expected);

        $user = $this->authenticationService->getUserByUsername('username');
        $this->assertEquals($expected, $user);
    }

    public function testGetValidTokenForUserWrongUser(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->userRepository->expects($this->once())
            ->method('find')
            ->with(0)
            ->willReturn(null);
        
        $this->authenticationService->getValidTokenForUser(0, TokenTypeEnum::TOKEN_RESET_PASSWORD, 'token');
    }

    public function testGetValidTokenForUserNotFound(): void
    {
        $this->expectException(InvalidTokenException::class);

        $user = $this->createMock(User::class);
        $this->userRepository->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn($user);
        
        $this->tokenVerificationRepository->expects($this->once())
            ->method('findValidTokenByUserAndType')
            ->with($user, TokenTypeEnum::TOKEN_RESET_PASSWORD)
            ->willReturn(null);
        
        $this->authenticationService->getValidTokenForUser(5, TokenTypeEnum::TOKEN_RESET_PASSWORD, 'token');
    }

    public function testGetValidTokenForUserWrongToken(): void
    {
        $this->expectException(InvalidTokenException::class);

        $user = $this->createMock(User::class);
        $this->userRepository->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn($user);

        $tokenVerification = $this->createMock(TokenVerification::class);
        $this->tokenVerificationRepository->expects($this->once())
            ->method('findValidTokenByUserAndType')
            ->with($user, TokenTypeEnum::TOKEN_RESET_PASSWORD)
            ->willReturn($tokenVerification);

        $tokenVerification->expects($this->once())
            ->method('getToken')
            ->willReturn('hashed_token');
        $this->tokenHasher->expects($this->once())
            ->method('verify')
            ->with('hashed_token', 'token')
            ->willReturn(false);
        
        $this->authenticationService->getValidTokenForUser(5, TokenTypeEnum::TOKEN_RESET_PASSWORD, 'token');
    }

    public function testGetValidTokenForUserSuccess(): void
    {
        $user = $this->createMock(User::class);
        $this->userRepository->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn($user);

        $tokenVerification = $this->createMock(TokenVerification::class);
        $this->tokenVerificationRepository->expects($this->once())
            ->method('findValidTokenByUserAndType')
            ->with($user, TokenTypeEnum::TOKEN_RESET_PASSWORD)
            ->willReturn($tokenVerification);

        $tokenVerification->expects($this->once())
            ->method('getToken')
            ->willReturn('hashed_token');
        $this->tokenHasher->expects($this->once())
            ->method('verify')
            ->with('hashed_token', 'token')
            ->willReturn(true);
        
        $actually = $this->authenticationService->getValidTokenForUser(5, TokenTypeEnum::TOKEN_RESET_PASSWORD, 'token');
        $this->assertEquals($tokenVerification, $actually);
    }

    public function testCreateToken(): void
    {
        $user = $this->createMock(User::class);
        $this->tokenHasher->expects($this->once())
            ->method('hash')
            ->willReturn('hashed_token');
        $createdToken = $this->authenticationService->createToken(TokenTypeEnum::TOKEN_EMAIL_VERIFICATION, $user);

        $this->assertEquals($user, $createdToken->tokenVerification->getUser());
        $this->assertEquals(TokenTypeEnum::TOKEN_EMAIL_VERIFICATION, $createdToken->tokenVerification->getType());
        $this->assertEquals('hashed_token', $createdToken->tokenVerification->getToken());
        $this->assertEquals(64, strlen($createdToken->unhashedToken));
    }

    public function testChangePasswordInvalid(): void
    {
        $this->expectException(InvalidPasswordException::class);

        $user = new User();
        $user->setPassword('password');

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'oldPassword')
            ->willReturn(false);

        $this->authenticationService->changePassword($user, 'oldPassword', 'newPassword');
    }

    public function testChangePasswordSuccess(): void
    {
        $user = new User();
        $user->setPassword('password');

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'password')
            ->willReturn(true);
        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'newPassword')
            ->willReturn('hashed_password');
        
        $newUser = $this->authenticationService->changePassword($user, 'password', 'newPassword');
        $this->assertEquals('hashed_password', $user->getPassword());
    }
}
