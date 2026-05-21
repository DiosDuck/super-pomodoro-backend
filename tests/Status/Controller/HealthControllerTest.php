<?php

declare(strict_types = 1);

namespace App\Tests\Status\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class HealthControllerTest extends WebTestCase
{
    public function testPingSucceeds(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/ping');

        $this->assertResponseIsSuccessful();

        $payload = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('Success', $payload['message']);
        $this->assertSame('OK', $payload['status']);
    }

    public function testPingFailsWhenFailFlagIsPresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/ping', ['fail' => '1']);

        $this->assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);

        $payload = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('Crit', $payload['message']);
        $this->assertSame('CRIT', $payload['status']);
    }

    public function testDatabaseSucceedsWhenConnectionIsHealthy(): void
    {
        $client = static::createClient();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1');

        static::getContainer()->set('doctrine.dbal.default_connection', $connection);

        $client->request('GET', '/api/health/database');

        $this->assertResponseIsSuccessful();

        $payload = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('Success', $payload['message']);
        $this->assertSame('OK', $payload['status']);
    }

    public function testDatabaseFailsWhenConnectionThrows(): void
    {
        $client = static::createClient();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willThrowException(new \RuntimeException('connection refused'));

        static::getContainer()->set('doctrine.dbal.default_connection', $connection);

        $client->request('GET', '/api/health/database');

        $this->assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);

        $payload = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('Service Unavailable', $payload['message']);
        $this->assertSame('CRIT', $payload['status']);
    }
}
