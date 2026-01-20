<?php

declare(strict_types=1);

namespace App\Authentication\Utils\Builder;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class TemplatedMailBuilder
{
    private TemplatedEmail $email;

    public function __construct(
    ) {
        $this->email = new TemplatedEmail();
    }

    public function createNewTemplatedEmail(): self
    {
        $this->email = new TemplatedEmail();

        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->email?->subject($subject);

        return $this;
    }

    public function setHtmlTemplate(string $template): self
    {
        $this->email?->htmlTemplate($template);

        return $this;
    }

    public function setContext(array $context): self
    {
        $this->email?->context($context);

        return $this;
    }

    public function getMail(): TemplatedEmail
    {
        return $this->email;
    }
}