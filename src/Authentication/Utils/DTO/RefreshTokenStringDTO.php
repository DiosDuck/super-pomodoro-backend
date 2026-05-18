<?php

declare(strict_types=1);

namespace App\Authentication\Utils\DTO;

class RefreshTokenStringDTO
{
    private readonly string $selector;
    private readonly string $verifier;

    public function __construct(
        private readonly string $plaintext,
        private readonly string $delimiter,
    ) {
        $parts = explode($this->delimiter, $this->plaintext, 2);
        $this->selector = $parts[0];
        $this->verifier = $parts[1] ?? '';
    }

    public function getPlaintext(): string
    {
        return $this->plaintext;
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getVerifier(): string
    {
        return $this->verifier;
    }
}
