<?php

declare(strict_types=1);

namespace App\Tests\Pomodoro\Command;

use App\Authentication\Entity\User;
use App\Pomodoro\Entity\SessionSaved;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PomodoroCleanerCommandTest extends KernelTestCase
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
     * @return array<string, array{value: string}>
     */
    public static function invalidDaysOldProvider(): array
    {
        return [
            'non-digit value' => ['value' => 'abc'],
            'negative int value' => ['value' => '-5'],
        ];
    }

    #[DataProvider('invalidDaysOldProvider')]
    public function testCommandFailsOnInvalidDaysOld(string $value): void
    {
        $commandTester = $this->buildCommandTester();
        $exitCode = $commandTester->execute(['--days-old' => $value]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(
            '--days-old must be a positive integer of 1 or greater',
            $commandTester->getDisplay(),
        );
    }

    /**
     * @return array<string, array{args: array<string, string>, expectedDeleted: int}>
     */
    public static function deletionProvider(): array
    {
        return [
            'default cutoff (8 days)' => [
                'args' => [],
                'expectedDeleted' => 2,
            ],
            'custom 3 day cutoff' => [
                'args' => ['--days-old' => '3'],
                'expectedDeleted' => 3,
            ],
            'custom 15 day cutoff' => [
                'args' => ['--days-old' => '15'],
                'expectedDeleted' => 1,
            ],
        ];
    }

    /**
     * @param array<string, string> $args
     */
    #[DataProvider('deletionProvider')]
    public function testCommandDeletesSessionsOlderThanThreshold(array $args, int $expectedDeleted): void
    {
        $user = $this->createUser();
        foreach ([1, 5, 10, 20] as $ageInDays) {
            $this->createSession($user, $ageInDays);
        }

        $commandTester = $this->buildCommandTester();
        $commandTester->execute($args);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString(
            sprintf('%d rows were deleted', $expectedDeleted),
            $commandTester->getDisplay(),
        );
    }

    private function buildCommandTester(): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:pomodoro:cleaner');

        return new CommandTester($command);
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

    private function createSession(User $user, int $ageInDays): void
    {
        $session = new SessionSaved();
        $session->setUser($user);
        $session->setWorkTime(25);
        $session->setCreatedAt(new DateTimeImmutable("-{$ageInDays} days"));

        $this->em->persist($session);
        $this->em->flush();
    }
}
