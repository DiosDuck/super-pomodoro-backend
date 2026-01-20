<?php

declare(strict_types=1);

namespace App\Authentication\Service;

class VerifyEmailUrlService {
    const REGISTER_EMAIL_URL = '/auth/verify-email/register';
    const FORGOT_PASSWORD_URL = '/auth/verify-email/password-reset';

    public function __construct(
        private readonly string $frontendBaseUrl,
    ) {}

    public function getRegisterEmailUrl(string $token, int $userId): string
    {
        return $this->getUrl(
            self::REGISTER_EMAIL_URL,
            [
                'token' => $token,
                'id' => $userId
            ],
        );
    }

    public function getForgotPasswordUrl(string $token, int $userId): string
    {
        return $this->getUrl(
            self::FORGOT_PASSWORD_URL,
            [
                'token' => $token,
                'id' => $userId
            ],
        );
    }

    private function getUrl(
        string $url,
        array $queryParams,
    ): string
    {
        return sprintf(
            '%s%s?%s',
            $this->frontendBaseUrl,
            $url,
            http_build_query($queryParams),
        );
    }
}