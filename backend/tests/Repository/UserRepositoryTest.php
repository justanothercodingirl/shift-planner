<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(UserRepository::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSavedUserIsFoundByEmail(): void
    {
        $this->repository->save(new User('anna@example.com', 'hash', UserRole::Employee));

        $found = $this->repository->findOneByEmail('anna@example.com');

        self::assertNotNull($found);
        self::assertSame(UserRole::Employee, $found->getRole());
    }

    public function testUnknownEmailReturnsNull(): void
    {
        self::assertNull($this->repository->findOneByEmail('nobody@example.com'));
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $this->repository->save(new User('anna@example.com', 'hash', UserRole::Employee));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->repository->save(new User('anna@example.com', 'hash2', UserRole::Manager));
    }
}
