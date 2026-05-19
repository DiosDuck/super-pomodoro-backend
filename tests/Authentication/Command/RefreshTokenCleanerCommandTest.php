<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Command;

use App\Authentication\Entity\RefreshToken;
use App\Authentication\Entity\User;
use App\Authentication\Repository\RefreshTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RefreshTokenCleanerCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * @return array<string, array{args: array<string, int|string>, expectedDeleted: int, expectedRemaining: int}>
     */
    public static function commandArgsProvider(): array
    {
        return [
            'default cutoff (7 days)' => [
                'args' => [],
                'expectedDeleted' => 2,
                'expectedRemaining' => 2,
            ],
            'custom 1 day cutoff' => [
                'args' => ['--days-old' => 1],
                'expectedDeleted' => 3,
                'expectedRemaining' => 1,
            ],
        ];
    }

    /**
     * @param array<string, int|string> $args
     */
    #[DataProvider('commandArgsProvider')]
    public function testPurgesExpiredAndOldRevokedTokens(array $args, int $expectedDeleted, int $expectedRemaining): void
    {
        $user = $this->createUser();

        $this->createRefreshTokenRow($user, 'sel-A', 'ver-A', new DateTimeImmutable('+1 day'), null);
        $this->createRefreshTokenRow($user, 'sel-B', 'ver-B', new DateTimeImmutable('-1 day'), null);
        $this->createRefreshTokenRow($user, 'sel-C', 'ver-C', new DateTimeImmutable('+1 day'), new DateTimeImmutable('-10 days'));
        $this->createRefreshTokenRow($user, 'sel-D', 'ver-D', new DateTimeImmutable('+1 day'), new DateTimeImmutable('-2 days'));

        $application = new Application(self::$kernel);
        $command = $application->find('app:refresh-token:cleaner');
        $commandTester = new CommandTester($command);
        $commandTester->execute($args);

        $commandTester->assertCommandIsSuccessful();

        $repository = static::getContainer()->get(RefreshTokenRepository::class);
        $this->assertCount($expectedRemaining, $repository->findAll());
        $this->assertStringContainsString(
            sprintf('Deleted %d expired or old-revoked refresh tokens', $expectedDeleted),
            $commandTester->getDisplay(),
        );
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setUsername('username');
        $user->setPassword('password');
        $user->setEmail('user@email.com');
        $user->setDisplayName('Username');
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createRefreshTokenRow(
        User $user,
        string $selector,
        string $verifier,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $revokedAt,
    ): void {
        $hasher = static::getContainer()->get('security.password_hasher.refresh_token_hasher');

        $token = new RefreshToken();
        $token->setUser($user);
        $token->setSelector($selector);
        $token->setTokenHash($hasher->hash($verifier));
        $token->setIssuedAt(new DateTimeImmutable('-1 hour'));
        $token->setExpiresAt($expiresAt);
        $token->setRevokedAt($revokedAt);

        $this->em->persist($token);
        $this->em->flush();
    }
}
