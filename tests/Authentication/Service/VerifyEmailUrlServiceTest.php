<?php

declare(strict_types=1);

use App\Authentication\Service\VerifyEmailUrlService;
use PHPUnit\Framework\TestCase;

class VerifyEmailUrlServiceTest extends TestCase {
    const BASE_URL_TEST = 'http://test.com';
    private VerifyEmailUrlService $verifyEmailUrlService;

    public function setUp(): void
    {
        $this->verifyEmailUrlService = new VerifyEmailUrlService(
            self::BASE_URL_TEST,
        );
    }

    public function testGetRegisterEmailUrl(): void
    {
        $url = $this->verifyEmailUrlService->getRegisterEmailUrl('abcd', 1);
        
        $this->assertEquals('http://test.com/auth/verify-email/register?token=abcd&id=1', $url);
    }

    public function testGetForgotPasswordUrl(): void
    {
        $url = $this->verifyEmailUrlService->getForgotPasswordUrl('abcd', 1);
        
        $this->assertEquals('http://test.com/auth/verify-email/password-reset?token=abcd&id=1', $url);
    }
}
