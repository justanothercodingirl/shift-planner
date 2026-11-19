<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\UserFixtures;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixturesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $users;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->users = $container->get(UserRepository::class);
        $this->hasher = $container->get(UserPasswordHasherInterface::class);
        $this->em->getConnection()->beginTransaction();
        $fixtures = new UserFixtures($this->hasher);
        $fixtures->setReferenceRepository(new ReferenceRepository($this->em));
        $fixtures->load($this->em);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testCreatesOneManagerAndThreeEmployees(): void
    {
        $manager = $this->users->findOneByEmail('manager@example.com');

        self::assertNotNull($manager);
        self::assertSame(UserRole::Manager, $manager->getRole());
        self::assertSame(['ROLE_MANAGER'], $manager->getRoles());

        foreach (['anna', 'ben', 'clara'] as $name) {
            $user = $this->users->findOneByEmail($name.'@example.com');
            self::assertNotNull($user);
            self::assertSame(UserRole::Employee, $user->getRole());
        }
    }

    public function testPasswordIsStoredHashedAndValid(): void
    {
        $manager = $this->users->findOneByEmail('manager@example.com');

        self::assertNotNull($manager);
        self::assertNotSame(UserFixtures::DEMO_PASSWORD, $manager->getPassword());
        self::assertTrue($this->hasher->isPasswordValid($manager, UserFixtures::DEMO_PASSWORD));
    }
}
