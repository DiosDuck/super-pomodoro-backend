<?php

declare(strict_types=1);

namespace App\Authentication\Utils\Factory;

use Symfony\Component\HttpFoundation\Cookie;

class RefreshTokenCookieFactory
{
    public const COOKIE_NAME = 'refresh_token';
    public const PATH = '/api/auth/';

    public function __construct(
        private readonly int $refreshTokenTtl,
    ) {}

    public function build(string $plaintext): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue($plaintext)
            ->withPath(self::PATH)
            ->withHttpOnly(true)
            ->withSecure(true)
            ->withSameSite(Cookie::SAMESITE_STRICT)
            ->withExpires(new \DateTimeImmutable("+{$this->refreshTokenTtl} seconds"));
    }
}
