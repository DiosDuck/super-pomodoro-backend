<?php

declare(strict_types = 1);

namespace App\Health\Model;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Ping Schema',
    description: 'Ping Schema',
)]
class Ping {
    public function __construct(
        #[OA\Property(type: 'string', example: 'OK')]
        public string $message,
        #[OA\Property(type: 'integer', enum: [0, 1, 2], example: 0)]
        public Status $status = Status::OK,
    ) { }
}
