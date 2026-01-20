<?php

declare(strict_types=1);

namespace App\Authentication\Service;

use App\Authentication\Utils\Builder\TemplatedMailBuilder;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\BodyRendererInterface;

class MailSenderService
{
    public function __construct(
        private readonly TemplatedMailBuilder $templatedMailBuilder,
        private readonly BodyRendererInterface $bodyRenderer,
        private readonly MailerInterface $mailer,
    ) {}

    public function getTemplatedMailBuilder(): TemplatedMailBuilder
    {
        return $this->templatedMailBuilder;
    }

    public function sendTemplatedMail(TemplatedEmail $email): void
    {
        $this->bodyRenderer->render($email);
        $this->mailer->send($email);
    }
}
