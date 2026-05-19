<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Repository;

use App\Authentication\Entity\User;
use App\Authentication\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->userRepository = static::getContainer()->get(UserRepository::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testUpgradePasswordPersistsNewHash(): void
    {
        $user = new User();
        $user->setUsername('alice');
        $user->setPassword('old-hash');
        $user->setEmail('alice@example.com');
        $user->setDisplayName('Alice');
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);

        $this->em->persist($user);
        $this->em->flush();

        $this->userRepository->upgradePassword($user, 'new-hash');

        $this->em->clear();
        $refreshed = $this->userRepository->findOneBy(['username' => 'alice']);
        $this->assertSame('new-hash', $refreshed->getPassword());
    }

    public function testUpgradePasswordRejectsForeignUserInstance(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $foreignUser = $this->createMock(PasswordAuthenticatedUserInterface::class);
        $this->userRepository->upgradePassword($foreignUser, 'irrelevant');
    }
}
