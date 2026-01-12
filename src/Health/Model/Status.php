<?php

declare(strict_types = 1);

namespace App\Health\Model;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Status',
    title: 'Status Code',
    description: '0 = OK, 1 = WARN, 2 = CRIT',
)]
enum Status: int
{
    case OK = 0;
    case WARN = 1;
    case CRIT = 2;
}
