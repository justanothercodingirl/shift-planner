<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Exception\AssignmentNotFoundException;
use App\Exception\EmployeeInactiveException;
use App\Exception\EmployeeNotFoundException;
use App\Exception\RuleViolationException;
use App\Exception\ShiftNotFoundException;
use App\Repository\AssignmentRepository;
use App\Repository\EmployeeRepository;
use App\Repository\RosterRepository;
use App\Repository\ShiftRepository;
use App\Repository\UserRepository;
use App\Service\AssignmentService;
use App\Service\ShiftService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AssignmentServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AssignmentService $service;
    private Roster $roster;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(AssignmentService::class);
        $this->em->getConnection()->beginTransaction();

        $this->roster = new Roster(new \DateTimeImmutable('2031-02-03'));
        $container->get(RosterRepository::class)->save($this->roster);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testAssignLinksTheEmployeeToTheShift(): void
    {
        $shift = $this->shift(2);
        $anna = $this->employee('Anna');

        $assignment = $this->service->assign((int) $shift->getId(), (int) $anna->getId());

        self::assertNotNull($assignment->getId());
        self::assertSame($shift, $assignment->getShift());
        self::assertSame($anna, $assignment->getEmployee());
    }

    public function testUnknownShiftThrowsNotFound(): void
    {
        $anna = $this->employee('Anna');

        $this->expectException(ShiftNotFoundException::class);
        $this->service->assign(999999, (int) $anna->getId());
    }

    public function testUnknownEmployeeThrowsNotFound(): void
    {
        $shift = $this->shift(1);

        $this->expectException(EmployeeNotFoundException::class);
        $this->service->assign((int) $shift->getId(), 999999);
    }

    public function testDeactivatedEmployeeIsRejected(): void
    {
        $shift = $this->shift(1);
        $anna = $this->employee('Anna');
        $anna->deactivate();
        self::getContainer()->get(EmployeeRepository::class)->save($anna);

        $this->expectException(EmployeeInactiveException::class);
        $this->service->assign((int) $shift->getId(), (int) $anna->getId());
    }

    public function testAssigningTheSamePersonTwiceIsRejected(): void
    {
        $shift = $this->shift(2);
        $anna = $this->employee('Anna');
        $this->service->assign((int) $shift->getId(), (int) $anna->getId());

        $violations = $this->violationCodes(fn () => $this->service->assign((int) $shift->getId(), (int) $anna->getId()));

        self::assertSame(['ALREADY_ASSIGNED'], $violations);
    }

    public function testAFullShiftIsRejected(): void
    {
        $shift = $this->shift(1);
        $this->service->assign((int) $shift->getId(), (int) $this->employee('Anna')->getId());
        $ben = $this->employee('Ben');

        $violations = $this->violationCodes(fn () => $this->service->assign((int) $shift->getId(), (int) $ben->getId()));

        self::assertSame(['SHIFT_FULL'], $violations);
    }

    public function testAllViolationsAreReportedTogether(): void
    {
        $shift = $this->shift(1);
        $anna = $this->employee('Anna');
        $this->service->assign((int) $shift->getId(), (int) $anna->getId());

        $violations = $this->violationCodes(fn () => $this->service->assign((int) $shift->getId(), (int) $anna->getId()));

        sort($violations);
        self::assertSame(['ALREADY_ASSIGNED', 'SHIFT_FULL'], $violations);
    }

    public function testUnassignRemovesTheAssignment(): void
    {
        $shift = $this->shift(1);
        $assignment = $this->service->assign((int) $shift->getId(), (int) $this->employee('Anna')->getId());
        $id = (int) $assignment->getId();

        $this->service->unassign($id);

        self::assertNull(self::getContainer()->get(AssignmentRepository::class)->find($id));
    }

    public function testUnassignOfUnknownIdThrowsNotFound(): void
    {
        $this->expectException(AssignmentNotFoundException::class);

        $this->service->unassign(999999);
    }

    public function testDeletingAShiftDeletesItsAssignments(): void
    {
        $shift = $this->shift(2);
        $assignment = $this->service->assign((int) $shift->getId(), (int) $this->employee('Anna')->getId());
        $assignmentId = (int) $assignment->getId();

        self::getContainer()->get(ShiftService::class)->delete((int) $shift->getId());
        $this->em->clear();

        self::assertNull(self::getContainer()->get(AssignmentRepository::class)->find($assignmentId));
    }

    /** @return list<string> */
    private function violationCodes(callable $action): array
    {
        try {
            $action();
        } catch (RuleViolationException $exception) {
            return array_column($exception->getViolations(), 'code');
        }

        self::fail('Expected a RuleViolationException.');
    }

    private function shift(int $headcount): Shift
    {
        $shift = new Shift(
            $this->roster,
            new \DateTimeImmutable('2031-02-05'),
            new \DateTimeImmutable('09:00'),
            new \DateTimeImmutable('15:00'),
            Position::Barista,
            $headcount,
        );
        self::getContainer()->get(ShiftRepository::class)->save($shift);

        return $shift;
    }

    private function employee(string $name): Employee
    {
        $user = new User(strtolower($name).'@example.com', 'hash', UserRole::Employee);
        self::getContainer()->get(UserRepository::class)->save($user);
        $employee = new Employee($user, $name, Position::Barista, ContractType::PartTime, 20);
        self::getContainer()->get(EmployeeRepository::class)->save($employee);

        return $employee;
    }
}
