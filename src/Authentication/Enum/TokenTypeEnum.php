<?php

declare(strict_types=1);

namespace App\Authentication\Enum;

enum TokenTypeEnum: string
{
    case TOKEN_EMAIL_VERIFICATION = 'token_email_verification';
    case TOKEN_RESET_PASSWORD = 'token_reset_password';
}
