<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Command;

use App\Authentication\Entity\TokenVerification;
use App\Authentication\Entity\User;
use App\Authentication\Utils\Enum\TokenTypeEnum;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Doctrine\ORM\EntityManagerInterface;

class TokenCleanerCommandTest extends KernelTestCase
{
    public function testDeleteUnusedTokens(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        
        $user1 = new User();
        $user1->setUsername('username1');
        $user1->setPassword('password');
        $user1->setEmail('user@email.com');
        $user1->setRoles(['ROLE_USER']);
        $user1->setDisplayName('User');
        $user1->setIsActive(false);

        $em->persist($user1);

        $user2 = new User();
        $user2->setUsername('username2');
        $user2->setPassword('password');
        $user2->setEmail('user@email.com');
        $user2->setRoles(['ROLE_USER']);
        $user2->setDisplayName('User');
        $user2->setIsActive(true);
        $user2->setActivatedAt(new DateTimeImmutable());

        $em->persist($user2);

        $user3 = new User();
        $user3->setUsername('username3');
        $user3->setPassword('password');
        $user3->setEmail('user@email.com');
        $user3->setRoles(['ROLE_USER']);
        $user3->setDisplayName('User');
        $user3->setIsActive(false);

        $em->persist($user3);

        $token1 = new TokenVerification();
        $token1->setUser($user1);
        $token1->setType(TokenTypeEnum::TOKEN_EMAIL_VERIFICATION);
        $token1->setExpiresAt(new DateTimeImmutable("-1 minute"));
        $token1->setIsUsed(false);
        $token1->setToken('abcdef');

        $em->persist($token1);

        $token2 = new TokenVerification();
        $token2->setUser($user2);
        $token2->setType(TokenTypeEnum::TOKEN_EMAIL_VERIFICATION);
        $token2->setExpiresAt(new DateTimeImmutable("-3 minutes"));
        $token2->setIsUsed(true);
        $token2->setToken('abcdef');

        $em->persist($token2);

        $token3 = new TokenVerification();
        $token3->setUser($user2);
        $token3->setType(TokenTypeEnum::TOKEN_RESET_PASSWORD);
        $token3->setExpiresAt(new DateTimeImmutable("15 minutes"));
        $token3->setIsUsed(false);
        $token3->setToken('abcdef');

        $em->persist($token3);

        $token4 = new TokenVerification();
        $token4->setUser($user2);
        $token4->setType(TokenTypeEnum::TOKEN_RESET_PASSWORD);
        $token4->setExpiresAt(new DateTimeImmutable("-15 minutes"));
        $token4->setIsUsed(false);
        $token4->setToken('abcdef');

        $em->persist($token4);
        
        $token5 = new TokenVerification();
        $token5->setUser($user3);
        $token5->setType(TokenTypeEnum::TOKEN_EMAIL_VERIFICATION);
        $token5->setExpiresAt(new DateTimeImmutable("20 minutes"));
        $token5->setIsUsed(false);
        $token5->setToken('abcdef');

        $em->persist($token5);
        $em->flush();

        $application = new Application(self::$kernel);

        $command = $application->find('app:token:cleaner');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Deleted 1 unconfirmed users', $output);
        $this->assertStringContainsString('Deleted 3 used or expired tokens', $output);
        $this->assertStringContainsString('Finish deleting command', $output);
    }
}
