<?php

declare(strict_types=1);

namespace App\Authentication\Security;

use App\Authentication\Entity\User;
use App\Authentication\Service\RefreshTokenService;
use App\Authentication\Utils\Factory\RefreshTokenCookieFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly AuthenticationSuccessHandlerInterface $authenticationSuccessHandler,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly RefreshTokenCookieFactory $cookieFactory,
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $response = $this->authenticationSuccessHandler->onAuthenticationSuccess($request, $token);

        $user = $token->getUser();
        if (!$user instanceof User) {
            return $response;
        }

        $plaintext = $this->refreshTokenService->issueRefreshToken($user);
        $response->headers->setCookie($this->cookieFactory->build($plaintext));

        return $response;
    }
}
