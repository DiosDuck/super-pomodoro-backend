<?php

declare(strict_types=1);

namespace App\Authentication\Security;

use App\Authentication\Entity\User;
use App\Authentication\Service\RefreshTokenService;
use App\Authentication\Utils\Factory\RefreshTokenCookieFactory;
use App\Authentication\Utils\Exception\RefreshTokenExpiredException;
use App\Authentication\Utils\Exception\RefreshTokenNotFoundException;
use App\Authentication\Utils\Exception\RefreshTokenReuseException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class RefreshTokenAuthenticator extends AbstractAuthenticator
{
    public const REFRESH_ENDPOINT = '/api/auth/refresh';
    private const ROTATED_PLAINTEXT_REQUEST_ATTR = '_rotated_refresh_token_plaintext';

    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RateLimiterFactoryInterface $refreshTokenLimiter,
        private readonly RefreshTokenCookieFactory $cookieFactory,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && $request->getPathInfo() === self::REFRESH_ENDPOINT;
    }

    public function authenticate(Request $request): Passport
    {
        $presentedRefreshToken = $request->cookies->get(RefreshTokenCookieFactory::COOKIE_NAME);
        if ($presentedRefreshToken === null || $presentedRefreshToken === '') {
            throw new CustomUserMessageAuthenticationException('Missing refresh token.');
        }

        $limiter = $this->refreshTokenLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyLoginAttemptsAuthenticationException();
        }

        try {
            $result = $this->refreshTokenService->rotateRefreshToken($presentedRefreshToken);
        } catch (RefreshTokenReuseException|RefreshTokenExpiredException|RefreshTokenNotFoundException $e) {
            throw new CustomUserMessageAuthenticationException($e->getMessage(), previous: $e);
        }

        $request->attributes->set(self::ROTATED_PLAINTEXT_REQUEST_ATTR, $result->plaintext);

        return new SelfValidatingPassport(
            new UserBadge($result->user->getUserIdentifier())
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Invalid refresh token.'], Response::HTTP_UNAUTHORIZED);
        }

        $jwt = $this->jwtManager->create($user);
        $rotatedPlaintext = (string) $request->attributes->get(self::ROTATED_PLAINTEXT_REQUEST_ATTR);

        $response = new JsonResponse(['token' => $jwt]);
        $response->headers->setCookie($this->cookieFactory->build($rotatedPlaintext));

        return $response;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(['message' => 'Too many requests.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $response = new JsonResponse(['message' => 'Invalid refresh token.'], Response::HTTP_UNAUTHORIZED);
        $response->headers->clearCookie(RefreshTokenCookieFactory::COOKIE_NAME, RefreshTokenCookieFactory::PATH);

        return $response;
    }
}
