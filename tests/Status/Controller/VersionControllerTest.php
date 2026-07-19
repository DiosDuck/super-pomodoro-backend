<?php

declare(strict_types = 1);

namespace App\Tests\Status\Controller;

use App\Status\Service\PHPVersionService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VersionControllerTest extends WebTestCase
{
    public function testAppVersionReturnsConfiguredVersion(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/version/app');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $payload = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('v1.0.0-test', $payload['message']);
        $this->assertSame('OK', $payload['status']);
    }

    public function testPHPVersionReturnsPHPInfoVersion(): void
    {
        $client = static::createClient();

        $phpSessionMock = $this->createMock(PHPVersionService::class);
        $phpSessionMock->expects($this->once())
            ->method('getPHPVersion')
            ->willReturn('8.3');
        $client->getContainer()->set(PHPVersionService::class, $phpSessionMock);
        
        $client->request('GET', '/api/version/php');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $payload = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('8.3', $payload['message']);
        $this->assertSame('OK', $payload['status']);
    }
}
