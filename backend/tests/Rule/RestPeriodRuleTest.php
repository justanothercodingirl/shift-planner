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
use App\Rule\RestPeriodRule;
use PHPUnit\Framework\TestCase;

final class RestPeriodRuleTest extends TestCase
{
    public function testReportsTooLittleRestSinceThePreviousShift(): void
    {
        // The previous shift ends 22:00, the new one starts 07:00: 9 hours.
        $previous = $this->shift('2031-02-04', '14:00', '22:00');
        $new = $this->shift('2031-02-05', '07:00', '13:00');

        $violation = $this->rule([$previous])->check($this->employee(), $new);

        self::assertNotNull($violation);
        self::assertSame('REST_PERIOD', $violation->code);
        self::assertSame('Only 9 hours rest since the previous shift', $violation->reason);
    }

    public function testExactlyTheMinimumRestIsAllowed(): void
    {
        $previous = $this->shift('2031-02-04', '14:00', '22:00');

        self::assertNull($this->rule([$previous])->check($this->employee(), $this->shift('2031-02-05', '09:00', '15:00')));
    }

    public function testMoreThanTheMinimumRestIsAllowed(): void
    {
        $previous = $this->shift('2031-02-04', '14:00', '22:00');

        self::assertNull($this->rule([$previous])->check($this->employee(), $this->shift('2031-02-05', '13:00', '19:00')));
    }

    public function testReportsTooLittleRestBeforeTheNextShift(): void
    {
        // The new shift ends 22:00, the next one starts 06:00 the next day: 8 hours.
        $next = $this->shift('2031-02-06', '06:00', '10:00');
        $new = $this->shift('2031-02-05', '14:00', '22:00');

        $violation = $this->rule([$next])->check($this->employee(), $new);

        self::assertNotNull($violation);
        self::assertSame('Only 8 hours rest before the next shift', $violation->reason);
    }

    public function testAnOvernightPreviousShiftIsMeasuredFromItsRealEnd(): void
    {
        // Tuesday 18:00 to Wednesday 02:00, then Wednesday 10:00: 8 hours.
        $previous = $this->shift('2031-02-04', '18:00', '02:00');

        $violation = $this->rule([$previous])->check($this->employee(), $this->shift('2031-02-05', '10:00', '16:00'));

        self::assertNotNull($violation);
        self::assertSame('Only 8 hours rest since the previous shift', $violation->reason);
    }

    public function testShiftsThatOnlyTouchHaveNoRestAtAll(): void
    {
        $previous = $this->shift('2031-02-05', '08:00', '14:00');

        $violation = $this->rule([$previous])->check($this->employee(), $this->shift('2031-02-05', '14:00', '18:00'));

        self::assertNotNull($violation);
        self::assertSame('Only 0 hours rest since the previous shift', $violation->reason);
    }

    public function testOverlappingShiftsAreLeftToTheOverlapRule(): void
    {
        $existing = $this->shift('2031-02-05', '10:00', '16:00');

        self::assertNull($this->rule([$existing])->check($this->employee(), $this->shift('2031-02-05', '14:00', '18:00')));
    }

    public function testFractionalHoursAreShownWithOneDecimal(): void
    {
        $previous = $this->shift('2031-02-04', '14:00', '22:00');

        $violation = $this->rule([$previous])->check($this->employee(), $this->shift('2031-02-05', '07:30', '13:00'));

        self::assertNotNull($violation);
        self::assertSame('Only 9.5 hours rest since the previous shift', $violation->reason);
    }

    public function testOneHourIsWrittenInTheSingular(): void
    {
        $previous = $this->shift('2031-02-04', '14:00', '22:00');

        $violation = $this->rule([$previous])->check($this->employee(), $this->shift('2031-02-04', '23:00', '23:59'));

        self::assertNotNull($violation);
        self::assertSame('Only 1 hour rest since the previous shift', $violation->reason);
    }

    public function testTheMinimumIsConfigurable(): void
    {
        $previous = $this->shift('2031-02-04', '14:00', '22:00');
        $nineHoursLater = $this->shift('2031-02-05', '07:00', '13:00');
        $sevenHoursLater = $this->shift('2031-02-05', '05:00', '11:00');

        self::assertNull($this->rule([$previous], 8.0)->check($this->employee(), $nineHoursLater));
        self::assertNotNull($this->rule([$previous], 8.0)->check($this->employee(), $sevenHoursLater));
    }

    public function testTheShortestRestIsTheOneReported(): void
    {
        $previous = $this->shift('2031-02-04', '14:00', '22:00');   // 9 hours before the new shift
        $next = $this->shift('2031-02-05', '18:00', '23:00');        // 5 hours after the new shift
        $new = $this->shift('2031-02-05', '07:00', '13:00');

        $violation = $this->rule([$previous, $next])->check($this->employee(), $new);

        self::assertNotNull($violation);
        self::assertSame('Only 5 hours rest before the next shift', $violation->reason);
    }

    public function testShiftsFromAnotherRosterWeekCountToo(): void
    {
        // Sunday of one week and Monday of the next week belong to different rosters.
        $sunday = $this->shift('2031-02-09', '16:00', '23:00', new Roster(new \DateTimeImmutable('2031-02-03')));
        $monday = $this->shift('2031-02-10', '06:00', '12:00', new Roster(new \DateTimeImmutable('2031-02-10')));

        $violation = $this->rule([$sunday])->check($this->employee(), $monday);

        self::assertNotNull($violation);
        self::assertSame('Only 7 hours rest since the previous shift', $violation->reason);
    }

    public function testTheShiftItselfIsIgnored(): void
    {
        $shift = $this->shift('2031-02-05', '10:00', '16:00');

        self::assertNull($this->rule([$shift])->check($this->employee(), $shift));
    }

    public function testAnEmployeeWithoutOtherShiftsIsAccepted(): void
    {
        self::assertNull($this->rule([])->check($this->employee(), $this->shift('2031-02-05', '10:00', '16:00')));
    }

    public function testLooksFarEnoughAroundTheShiftForTheConfiguredMinimum(): void
    {
        $this->expectDatesRequested(11.0, '2031-02-03', '2031-02-07');   // 11 hours: two days either side
        $this->expectDatesRequested(36.0, '2031-02-02', '2031-02-08');   // 36 hours: three days either side
    }

    private function expectDatesRequested(float $minimumHours, string $from, string $to): void
    {
        $assignments = $this->createMock(AssignmentRepository::class);
        $employee = $this->employee();
        $assignments->expects(self::once())
            ->method('findShiftsOfEmployeeBetweenDates')
            ->with(
                $employee,
                self::callback(static fn (\DateTimeImmutable $day): bool => $from === $day->format('Y-m-d')),
                self::callback(static fn (\DateTimeImmutable $day): bool => $to === $day->format('Y-m-d')),
            )
            ->willReturn([]);

        (new RestPeriodRule($assignments, $minimumHours))->check($employee, $this->shift('2031-02-05', '10:00', '16:00'));
    }

    /** @param list<Shift> $employeesShifts */
    private function rule(array $employeesShifts, float $minimumHours = 11.0): RestPeriodRule
    {
        $assignments = $this->createStub(AssignmentRepository::class);
        $assignments->method('findShiftsOfEmployeeBetweenDates')->willReturn($employeesShifts);

        return new RestPeriodRule($assignments, $minimumHours);
    }

    private function employee(): Employee
    {
        return new Employee(
            new User('anna@example.com', 'hash', UserRole::Employee),
            'Anna',
            Position::Barista,
            ContractType::PartTime,
            20,
        );
    }

    private function shift(string $date, string $start, string $end, ?Roster $roster = null): Shift
    {
        return new Shift(
            $roster ?? new Roster(new \DateTimeImmutable('2031-02-03')),
            new \DateTimeImmutable($date),
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            Position::Barista,
            1,
        );
    }
}
