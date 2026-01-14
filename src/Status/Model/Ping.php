<?php

declare(strict_types = 1);

namespace App\Status\Model;

use Nelmio\ApiDocBundle\Model\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Ping Schema',
    description: 'Ping Schema',
)]
class Ping {
    #[OA\Property(example: 'Success')]
    public string $message;
    public StatusCode $status;
    
    public function __construct(
        StatusCode $status = StatusCode::OK,
        ?string $message = null,
    ) { 

        if (!$message) {
            $message = $status->toMessage();
        }

        $this->status = $status;
        $this->message = $message;
    }
}
