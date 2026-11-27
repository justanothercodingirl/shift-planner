<?php

declare(strict_types=1);

namespace App\Tests\Mail;

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

/**
 * Builds a small roster in the test database for the mail, PDF and queue tests.
 * The using test class is a KernelTestCase that runs inside a rolled-back transaction.
 */
trait RosterTestData
{
    /**
     * A published roster for the week of Monday 2031-02-03: Anna works Monday morning
     * (barista, one of two places still open), Ben works Monday evening into the night
     * (overnight), nobody has the Wednesday kitchen shift. Clara has no shift at all.
     *
     * @return array{roster: Roster, anna: Employee, ben: Employee, clara: Employee}
     */
    private function createRoster(): array
    {
        $roster = new Roster(new \DateTimeImmutable('2031-02-03'));
        $roster->publish();
        self::getContainer()->get(RosterRepository::class)->save($roster);

        $anna = $this->createEmployee('Anna', Position::Barista);
        $ben = $this->createEmployee('Ben', Position::Service);
        $clara = $this->createEmployee('Clara', Position::Kitchen);

        $morning = $this->createShift($roster, '2031-02-03', '08:00', '14:00', Position::Barista, 2);
        $night = $this->createShift($roster, '2031-02-03', '18:00', '02:00', Position::Service, 1);
        $this->createShift($roster, '2031-02-05', '10:00', '16:00', Position::Kitchen, 1);
        $this->assign($morning, $anna);
        $this->assign($night, $ben);

        return ['roster' => $roster, 'anna' => $anna, 'ben' => $ben, 'clara' => $clara];
    }

    private function createEmployee(string $name, Position $position = Position::Barista, bool $active = true): Employee
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $name));
        $user = new User($slug.'-mail@example.com', 'hash', UserRole::Employee);
        self::getContainer()->get(UserRepository::class)->save($user);
        $employee = new Employee($user, $name, $position, ContractType::PartTime, 20);
        if (!$active) {
            $employee->deactivate();
        }
        self::getContainer()->get(EmployeeRepository::class)->save($employee);

        return $employee;
    }

    private function createShift(Roster $roster, string $date, string $start, string $end, Position $position, int $headcount): Shift
    {
        $shift = new Shift(
            $roster,
            new \DateTimeImmutable($date),
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            $position,
            $headcount,
        );
        self::getContainer()->get(ShiftRepository::class)->save($shift);

        return $shift;
    }

    private function assign(Shift $shift, Employee $employee): void
    {
        self::getContainer()->get(AssignmentRepository::class)->save(new Assignment($shift, $employee));
    }
}
