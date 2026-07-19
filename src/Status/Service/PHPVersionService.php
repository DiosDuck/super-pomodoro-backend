<?php

declare(strict_types=1);

namespace App\Status\Service;

class PHPVersionService {
    public function getPHPVersion(): string
    {
        return phpversion();
    }
}
