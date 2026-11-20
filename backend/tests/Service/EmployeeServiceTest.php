<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CreateEmployeeRequest;
use App\Dto\UpdateEmployeeRequest;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Exception\EmailAlreadyUsedException;
use App\Exception\EmployeeNotFoundException;
use App\Service\EmployeeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class EmployeeServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private EmployeeService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(EmployeeService::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testCreateWritesUserAndEmployeeWithHashedPassword(): void
    {
        $employee = $this->service->create($this->createRequest('new@example.com'));

        self::assertNotNull($employee->getId());
        self::assertSame('Nina', $employee->getName());
        self::assertTrue($employee->isActive());

        $user = $employee->getUser();
        self::assertSame('new@example.com', $user->getEmail());
        self::assertSame(UserRole::Employee, $user->getRole());
        self::assertNotSame('secret-pass', $user->getPassword());
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'secret-pass'));
    }

    public function testCreateWithTakenEmailIsRejected(): void
    {
        $this->service->create($this->createRequest('taken@example.com'));

        $this->expectException(EmailAlreadyUsedException::class);
        $this->service->create($this->createRequest('taken@example.com'));
    }

    public function testUpdateChangesTheEditableFields(): void
    {
        $employee = $this->service->create($this->createRequest('upd@example.com'));

        $updated = $this->service->update(
            (int) $employee->getId(),
            new UpdateEmployeeRequest('Nina Neu', Position::Kitchen, ContractType::FullTime, 40),
        );

        self::assertSame('Nina Neu', $updated->getName());
        self::assertSame(Position::Kitchen, $updated->getPosition());
        self::assertSame(ContractType::FullTime, $updated->getContractType());
        self::assertSame(40, $updated->getMaxWeeklyHours());
        self::assertSame('upd@example.com', $updated->getUser()->getEmail());
    }

    public function testDeactivateKeepsTheRecordAndCanBeRepeated(): void
    {
        $employee = $this->service->create($this->createRequest('off@example.com'));
        $id = (int) $employee->getId();

        $this->service->deactivate($id);
        $again = $this->service->deactivate($id);

        self::assertFalse($again->isActive());
        self::assertSame($id, $this->service->get($id)->getId());
    }

    public function testUnknownIdThrowsNotFound(): void
    {
        $this->expectException(EmployeeNotFoundException::class);

        $this->service->get(999999);
    }

    public function testListIsOrderedByName(): void
    {
        $this->service->create($this->createRequest('z@example.com', 'Zed'));
        $this->service->create($this->createRequest('a@example.com', 'Amy'));

        $names = array_map(static fn ($e): string => $e->getName(), $this->service->list());

        self::assertSame(['Amy', 'Zed'], array_values(array_intersect($names, ['Amy', 'Zed'])));
    }

    private function createRequest(string $email, string $name = 'Nina'): CreateEmployeeRequest
    {
        return new CreateEmployeeRequest($email, 'secret-pass', $name, Position::Barista, ContractType::PartTime, 20);
    }
}
