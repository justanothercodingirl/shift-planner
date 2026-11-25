<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Employee;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EmployeeRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private EmployeeRepository $employees;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->employees = $container->get(EmployeeRepository::class);
        $this->em->getConnection()->beginTransaction();

        $this->user = new User('anna@example.com', 'hash', UserRole::Employee);
        $container->get(UserRepository::class)->save($this->user);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSavedEmployeeCanBeFoundWithItsValues(): void
    {
        $employee = new Employee($this->user, 'Anna', Position::Barista, ContractType::PartTime, 20);
        $this->employees->save($employee);
        $this->em->clear();

        $found = $this->employees->find($employee->getId());

        self::assertNotNull($found);
        self::assertSame('Anna', $found->getName());
        self::assertSame(Position::Barista, $found->getPosition());
        self::assertSame(ContractType::PartTime, $found->getContractType());
        self::assertSame(20, $found->getMaxWeeklyHours());
        self::assertTrue($found->isActive());
        self::assertSame('anna@example.com', $found->getUser()->getEmail());
    }

    public function testRemovedEmployeeIsGone(): void
    {
        $employee = new Employee($this->user, 'Anna', Position::Barista, ContractType::PartTime, 20);
        $this->employees->save($employee);
        $id = $employee->getId();

        $this->employees->remove($employee);

        self::assertNull($this->employees->find($id));
    }

    public function testFindsTheEmployeeBelongingToAUser(): void
    {
        $employee = new Employee($this->user, 'Anna', Position::Barista, ContractType::PartTime, 20);
        $this->employees->save($employee);

        self::assertSame($employee, $this->employees->findOneByUser($this->user));
    }

    public function testAUserWithoutAnEmployeeFindsNothing(): void
    {
        self::assertNull($this->employees->findOneByUser($this->user));
    }

    public function testOneUserCannotHaveTwoEmployees(): void
    {
        $this->employees->save(new Employee($this->user, 'Anna', Position::Barista, ContractType::PartTime, 20));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->employees->save(new Employee($this->user, 'Anna Two', Position::Kitchen, ContractType::MiniJob, 10));
    }
}
