<?php

declare(strict_types=1);

namespace App\Tests\Utils\Controller;

use App\Authentication\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CleanWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    public function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();

        $this->client->enableReboot();

        $this->client->getContainer()->get('cache.app')->clear();
        $this->client->getContainer()->get('cache.rate_limiter')->clear();

        /** @var EntityManagerInterface $em */
        $em = $this->client->getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        if ($metadata) {
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
        }

        $em->clear();
    }

    public function tearDown(): void
    {
        if (isset($this->client)) {
            $em = $this->client->getContainer()->get('doctrine')->getManager();
            $em->close();
        }

        self::ensureKernelShutdown();
        parent::tearDown();
    }

    protected function saveUser(User $user): User
    {
        /** @var EntityManagerInterface $em */
        $em = $this->client->getContainer()->get('doctrine')->getManager();

        $passwordHasher = $this->client->getContainer()->get(UserPasswordHasherInterface::class);

        $user->setPassword(
            $passwordHasher->hashPassword($user, $user->getPassword()),
        );

        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function saveObjectToDatabase(object $obj): object
    {
        /** @var EntityManagerInterface $em */
        $em = $this->client->getContainer()->get('doctrine')->getManager();

        $em->persist($obj);
        $em->flush();

        return $obj;
    }

    protected function testAndGetLoginToken(string $user, string $password): string
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'username' => $user,
                'password' => $password,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();

        return $this->testAndGetJsonResponse('token');
    }

    protected function testAndGetJsonResponse(string $key): mixed
    {
        /** @var Response $response */
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey($key, $data);
        return $data[$key];
    }
}
