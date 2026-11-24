<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Repository\AssignmentRepository;
use App\Repository\EmployeeRepository;
use App\Repository\RosterRepository;
use App\Repository\ShiftRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AssignmentRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AssignmentRepository $assignments;
    private Roster $roster;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->assignments = $container->get(AssignmentRepository::class);
        $this->em->getConnection()->beginTransaction();

        $this->roster = new Roster(new \DateTimeImmutable('2031-02-03'));
        $container->get(RosterRepository::class)->save($this->roster);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testFindsTheEmployeesShiftsInTheDateRangeInOrder(): void
    {
        $anna = $this->employee('Anna');
        $this->assign($anna, $this->shift('2031-02-06', '10:00', '14:00'));
        $this->assign($anna, $this->shift('2031-02-04', '16:00', '22:00'));
        $this->assign($anna, $this->shift('2031-02-04', '08:00', '12:00'));

        $found = $this->assignments->findShiftsOfEmployeeBetweenDates(
            $anna,
            new \DateTimeImmutable('2031-02-04'),
            new \DateTimeImmutable('2031-02-06'),
        );

        self::assertSame(
            ['02-04 08:00', '02-04 16:00', '02-06 10:00'],
            array_map(static fn (Shift $s): string => $s->getDate()->format('m-d').' '.$s->getStartTime()->format('H:i'), $found),
        );
    }

    public function testBothEndsOfTheRangeAreIncludedAndOutsideDatesAreNot(): void
    {
        $anna = $this->employee('Anna');
        $this->assign($anna, $this->shift('2031-02-03', '09:00', '13:00'));
        $this->assign($anna, $this->shift('2031-02-04', '09:00', '13:00'));
        $this->assign($anna, $this->shift('2031-02-05', '09:00', '13:00'));
        $this->assign($anna, $this->shift('2031-02-06', '09:00', '13:00'));

        $found = $this->assignments->findShiftsOfEmployeeBetweenDates(
            $anna,
            new \DateTimeImmutable('2031-02-04'),
            new \DateTimeImmutable('2031-02-05'),
        );

        self::assertSame(['2031-02-04', '2031-02-05'], array_map(static fn (Shift $s): string => $s->getDate()->format('Y-m-d'), $found));
    }

    public function testShiftsOfOtherEmployeesAreNotReturned(): void
    {
        $anna = $this->employee('Anna');
        $ben = $this->employee('Ben');
        $this->assign($ben, $this->shift('2031-02-05', '09:00', '13:00'));

        $found = $this->assignments->findShiftsOfEmployeeBetweenDates(
            $anna,
            new \DateTimeImmutable('2031-02-01'),
            new \DateTimeImmutable('2031-02-28'),
        );

        self::assertSame([], $found);
    }

    public function testShiftsFromOtherRosterWeeksAreFoundToo(): void
    {
        $anna = $this->employee('Anna');
        $nextRoster = new Roster(new \DateTimeImmutable('2031-02-10'));
        self::getContainer()->get(RosterRepository::class)->save($nextRoster);
        $this->assign($anna, $this->shift('2031-02-09', '20:00', '23:00'));
        $this->assign($anna, $this->shift('2031-02-10', '07:00', '10:00', $nextRoster));

        $found = $this->assignments->findShiftsOfEmployeeBetweenDates(
            $anna,
            new \DateTimeImmutable('2031-02-09'),
            new \DateTimeImmutable('2031-02-10'),
        );

        self::assertCount(2, $found);
    }

    private function shift(string $date, string $start, string $end, ?Roster $roster = null): Shift
    {
        $shift = new Shift(
            $roster ?? $this->roster,
            new \DateTimeImmutable($date),
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            Position::Barista,
            5,
        );
        self::getContainer()->get(ShiftRepository::class)->save($shift);

        return $shift;
    }

    private function assign(Employee $employee, Shift $shift): void
    {
        $this->assignments->save(new Assignment($shift, $employee));
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
