<?php

declare(strict_types = 1);

namespace App\Status\Model;

enum StatusCode: string
{
    case OK = 'OK';
    case WARN = 'WARN';
    case CRIT = 'CRIT';

    public function toMessage(): string
    {
        return match($this) {
            StatusCode::OK => 'Success',
            StatusCode::WARN =>'Warning',
            StatusCode::CRIT =>'Crit',
        };
    }
}
