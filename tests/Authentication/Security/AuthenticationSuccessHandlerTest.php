<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Security;

use App\Authentication\Entity\User;
use App\Authentication\Security\AuthenticationSuccessHandler;
use App\Authentication\Service\RefreshTokenService;
use App\Authentication\Utils\Factory\RefreshTokenCookieFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class AuthenticationSuccessHandlerTest extends TestCase
{
    private AuthenticationSuccessHandler $handler;
    private AuthenticationSuccessHandlerInterface&MockObject $innerHandler;
    private RefreshTokenService&MockObject $refreshTokenService;
    private RefreshTokenCookieFactory&MockObject $cookieFactory;

    protected function setUp(): void
    {
        $this->innerHandler = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $this->refreshTokenService = $this->createMock(RefreshTokenService::class);
        $this->cookieFactory = $this->createMock(RefreshTokenCookieFactory::class);

        $this->handler = new AuthenticationSuccessHandler(
            $this->innerHandler,
            $this->refreshTokenService,
            $this->cookieFactory,
        );
    }

    public function testOnAuthenticationSuccessNotInstanceOfUser(): void
    {
        $request = $this->createMock(Request::class);
        $token = $this->createMock(TokenInterface::class);
        $nonUser = $this->createMock(UserInterface::class);
        $innerResponse = new Response();

        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($nonUser);

        $this->innerHandler->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($request, $token)
            ->willReturn($innerResponse);

        $this->refreshTokenService->expects($this->never())->method('issueRefreshToken');
        $this->cookieFactory->expects($this->never())->method('build');

        $response = $this->handler->onAuthenticationSuccess($request, $token);

        $this->assertSame($innerResponse, $response);
        $this->assertCount(0, $response->headers->getCookies());
    }

    public function testOnAuthenticationSuccessWithUserAttachesCookie(): void
    {
        $request = $this->createMock(Request::class);
        $token = $this->createMock(TokenInterface::class);
        $user = $this->createMock(User::class);
        $innerResponse = new Response();
        $cookie = Cookie::create('refresh_token')->withValue('selector.verifier');

        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->innerHandler->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($request, $token)
            ->willReturn($innerResponse);

        $this->refreshTokenService->expects($this->once())
            ->method('issueRefreshToken')
            ->with($user)
            ->willReturn('selector.verifier');

        $this->cookieFactory->expects($this->once())
            ->method('build')
            ->with('selector.verifier')
            ->willReturn($cookie);

        $response = $this->handler->onAuthenticationSuccess($request, $token);

        $this->assertSame($innerResponse, $response);
        $this->assertCount(1, $response->headers->getCookies());
        $this->assertSame($cookie, $response->headers->getCookies()[0]);
    }
}
