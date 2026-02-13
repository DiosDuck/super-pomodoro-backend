<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Security;

use App\Authentication\Entity\User;
use App\Authentication\Security\ActiveUserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;

class ActiveUserCheckerTest extends TestCase {
    private ActiveUserChecker $activeUserChecker;

    protected function setUp(): void
    {
        $this->activeUserChecker = new ActiveUserChecker();
    }

    public function testPreAuthNotUser(): void
    {
        $this->expectNotToPerformAssertions();
        $user = $this->createMock(UserInterface::class);

        $this->activeUserChecker->checkPreAuth($user);
    }

    public function testPreAuthInvactiveUser(): void
    {
        $this->expectException(BadCredentialsException::class);
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('isActive')
            ->willReturn(false);

        $this->activeUserChecker->checkPreAuth($user);
    }

    public function testPreAuthActiveUser(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $this->activeUserChecker->checkPreAuth($user);
    }

    public function testPostAuth(): void
    {
        $this->expectNotToPerformAssertions();
        $user = $this->createMock(UserInterface::class);

        $this->activeUserChecker->checkPostAuth($user);
    }
}
