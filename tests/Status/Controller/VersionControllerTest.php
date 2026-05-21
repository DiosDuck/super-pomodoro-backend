<?php

declare(strict_types = 1);

namespace App\Tests\Status\Controller;

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
}
