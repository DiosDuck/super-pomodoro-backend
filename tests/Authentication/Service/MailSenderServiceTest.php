<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Service;

use App\Authentication\Service\MailSenderService;
use App\Authentication\Utils\Builder\TemplatedMailBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\BodyRendererInterface;

class MailSenderServiceTest extends TestCase
{
    private MailSenderService $mailSenderService;
    private TemplatedMailBuilder&MockObject $templateMailBuilder;
    private BodyRendererInterface&MockObject $bodyRenderer;
    private MailerInterface&MockObject $mailer;

    public function setUp(): void
    {
        $this->templateMailBuilder = $this->createMock(TemplatedMailBuilder::class);
        $this->bodyRenderer = $this->createMock(BodyRendererInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->mailSenderService = new MailSenderService(
            $this->templateMailBuilder,
            $this->bodyRenderer,
            $this->mailer,
        );
    }

    public function testGetTemplatedMailBuilder(): void
    {
        $templatedBuilder = $this->mailSenderService->getTemplatedMailBuilder();
        $this->assertEquals($this->templateMailBuilder, $templatedBuilder);
    }

    public function testSendTemplatedMail(): void
    {
        $templatedMail = $this->createMock(TemplatedEmail::class);
        $this->bodyRenderer->expects($this->once())
            ->method('render')
            ->with($templatedMail);
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($templatedMail);
        
        $this->mailSenderService->sendTemplatedMail($templatedMail);
    }
}
