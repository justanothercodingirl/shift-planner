<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\EmployeeFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Employee;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Repository\EmployeeRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class EmployeeFixturesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private EmployeeRepository $employees;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->employees = $container->get(EmployeeRepository::class);
        $this->em->getConnection()->beginTransaction();

        $loader = new Loader();
        $loader->addFixture(new UserFixtures($container->get(UserPasswordHasherInterface::class)));
        $loader->addFixture(new EmployeeFixtures());
        (new ORMExecutor($this->em))->execute($loader->getFixtures(), true);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testEveryDemoUserGetsAnEmployeeIncludingTheManager(): void
    {
        self::assertCount(4, $this->employees->findAll());

        $manager = $this->employeeOf('manager@example.com');
        self::assertSame(Position::ShiftLead, $manager->getPosition());
        self::assertSame(ContractType::FullTime, $manager->getContractType());
        self::assertSame(40, $manager->getMaxWeeklyHours());
    }

    public function testDemoEmployeesUseTheSuggestedHoursPerContractType(): void
    {
        $anna = $this->employeeOf('anna@example.com');
        self::assertSame(Position::Barista, $anna->getPosition());
        self::assertSame(20, $anna->getMaxWeeklyHours());

        $ben = $this->employeeOf('ben@example.com');
        self::assertSame(Position::Service, $ben->getPosition());
        self::assertSame(ContractType::WorkingStudent, $ben->getContractType());
        self::assertSame(20, $ben->getMaxWeeklyHours());

        $clara = $this->employeeOf('clara@example.com');
        self::assertSame(Position::Kitchen, $clara->getPosition());
        self::assertSame(ContractType::MiniJob, $clara->getContractType());
        self::assertSame(10, $clara->getMaxWeeklyHours());
    }

    public function testAnnaCanAlsoWorkInServiceAndTheOthersOnlyTheirOwnPosition(): void
    {
        self::assertSame([Position::Service], $this->employeeOf('anna@example.com')->getAdditionalPositions());
        self::assertSame([], $this->employeeOf('ben@example.com')->getAdditionalPositions());
        self::assertSame([], $this->employeeOf('clara@example.com')->getAdditionalPositions());
        self::assertSame([], $this->employeeOf('manager@example.com')->getAdditionalPositions());
    }

    private function employeeOf(string $email): Employee
    {
        foreach ($this->employees->findAll() as $employee) {
            if ($employee->getUser()->getEmail() === $email) {
                return $employee;
            }
        }

        self::fail('No employee for '.$email);
    }
}
