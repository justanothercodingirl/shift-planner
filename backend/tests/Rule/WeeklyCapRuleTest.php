<?php

declare(strict_types=1);

namespace App\Tests\Rule;

use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Repository\AssignmentRepository;
use App\Rule\WeeklyCapRule;
use PHPUnit\Framework\TestCase;

final class WeeklyCapRuleTest extends TestCase
{
    public function testUnderTheCapIsAllowed(): void
    {
        // 8 + 6 = 14 of 20 hours.
        $others = [$this->shift('2031-02-03', '09:00', '17:00')];

        self::assertNull($this->rule($others)->check($this->employee(20), $this->shift('2031-02-05', '09:00', '15:00')));
    }

    public function testExactlyTheCapIsAllowed(): void
    {
        // 14 + 6 = 20 of 20 hours.
        $others = [$this->shift('2031-02-03', '09:00', '23:00')];

        self::assertNull($this->rule($others)->check($this->employee(20), $this->shift('2031-02-05', '09:00', '15:00')));
    }

    public function testOneHourOverTheCapIsReported(): void
    {
        // 15 + 6 = 21 of 20 hours.
        $others = [$this->shift('2031-02-03', '08:00', '23:00')];

        $violation = $this->rule($others)->check($this->employee(20), $this->shift('2031-02-05', '09:00', '15:00'));

        self::assertNotNull($violation);
        self::assertSame('WEEKLY_CAP', $violation->code);
        self::assertSame('This would bring Anna to 21 of 20 hours', $violation->reason);
    }

    public function testTheMessageMatchesTheSpecExample(): void
    {
        // 15 + 3 + 6 = 24 of 20 hours.
        $others = [$this->shift('2031-02-03', '08:00', '23:00'), $this->shift('2031-02-04', '09:00', '12:00')];

        $violation = $this->rule($others)->check($this->employee(20), $this->shift('2031-02-05', '09:00', '15:00'));

        self::assertNotNull($violation);
        self::assertSame('This would bring Anna to 24 of 20 hours', $violation->reason);
    }

    public function testPartialHoursAreShownWithOneDecimal(): void
    {
        // 15 + 5.5 = 20.5 of 20 hours.
        $others = [$this->shift('2031-02-03', '08:00', '23:00')];

        $violation = $this->rule($others)->check($this->employee(20), $this->shift('2031-02-05', '09:00', '14:30'));

        self::assertNotNull($violation);
        self::assertSame('This would bring Anna to 20.5 of 20 hours', $violation->reason);
    }

    public function testTheLimitComesFromTheEmployee(): void
    {
        $others = [$this->shift('2031-02-03', '08:00', '23:00')];

        self::assertNull($this->rule($others)->check($this->employee(40), $this->shift('2031-02-05', '09:00', '15:00')));
    }

    public function testOnlyTheShiftsOfTheSameMondayToSundayWeekAreRequested(): void
    {
        $this->expectDatesRequested('2031-02-03', '2031-02-09', '2031-02-05');
    }

    public function testTheWeekOfAMondayShiftStartsOnThatMonday(): void
    {
        $this->expectDatesRequested('2031-02-03', '2031-02-09', '2031-02-03');
    }

    public function testTheWeekOfASundayShiftEndsOnThatSunday(): void
    {
        $this->expectDatesRequested('2031-02-03', '2031-02-09', '2031-02-09');
    }

    public function testTheNextMondayBelongsToTheNextWeek(): void
    {
        $this->expectDatesRequested('2031-02-10', '2031-02-16', '2031-02-10');
    }

    public function testAnOvernightShiftCountsItsFullLengthToItsStartWeek(): void
    {
        // Sunday 20:00 to Monday 04:00 is 8 hours in the week of the Sunday: 13 + 8 = 21 of 20.
        $others = [$this->shift('2031-02-03', '08:00', '21:00')];

        $violation = $this->rule($others)->check($this->employee(20), $this->shift('2031-02-09', '20:00', '04:00'));

        self::assertNotNull($violation);
        self::assertSame('This would bring Anna to 21 of 20 hours', $violation->reason);
    }

    public function testTheShiftBeingAssignedIsNotCountedTwice(): void
    {
        $new = $this->shift('2031-02-05', '09:00', '19:00');

        self::assertNull($this->rule([$new])->check($this->employee(10), $new));
    }

    public function testNoOtherShiftsOnlyTheShiftItselfCounts(): void
    {
        self::assertNotNull($this->rule([])->check($this->employee(5), $this->shift('2031-02-05', '09:00', '15:00')));
        self::assertNull($this->rule([])->check($this->employee(6), $this->shift('2031-02-05', '09:00', '15:00')));
    }

    private function expectDatesRequested(string $monday, string $sunday, string $shiftDate): void
    {
        $assignments = $this->createMock(AssignmentRepository::class);
        $employee = $this->employee(20);
        $assignments->expects(self::once())
            ->method('findShiftsOfEmployeeBetweenDates')
            ->with(
                $employee,
                self::callback(static fn (\DateTimeImmutable $day): bool => $monday === $day->format('Y-m-d')),
                self::callback(static fn (\DateTimeImmutable $day): bool => $sunday === $day->format('Y-m-d')),
            )
            ->willReturn([]);

        (new WeeklyCapRule($assignments))->check($employee, $this->shift($shiftDate, '10:00', '16:00'));
    }

    /** @param list<Shift> $employeesShifts */
    private function rule(array $employeesShifts): WeeklyCapRule
    {
        $assignments = $this->createStub(AssignmentRepository::class);
        $assignments->method('findShiftsOfEmployeeBetweenDates')->willReturn($employeesShifts);

        return new WeeklyCapRule($assignments);
    }

    private function employee(int $maxWeeklyHours): Employee
    {
        return new Employee(
            new User('anna@example.com', 'hash', UserRole::Employee),
            'Anna',
            Position::Barista,
            ContractType::PartTime,
            $maxWeeklyHours,
        );
    }

    private function shift(string $date, string $start, string $end): Shift
    {
        return new Shift(
            new Roster(new \DateTimeImmutable('2031-02-03')),
            new \DateTimeImmutable($date),
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            Position::Barista,
            1,
        );
    }
}
