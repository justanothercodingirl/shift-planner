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
use App\Rule\OverlapRule;
use PHPUnit\Framework\TestCase;

final class OverlapRuleTest extends TestCase
{
    public function testReportsAShiftThatOverlapsOneTheEmployeeAlreadyWorks(): void
    {
        $existing = $this->shift('2031-02-05', '10:00', '16:00');
        $new = $this->shift('2031-02-05', '14:00', '18:00');

        $violation = $this->rule([$existing])->check($this->employee(), $new);

        self::assertNotNull($violation);
        self::assertSame('OVERLAP', $violation->code);
        self::assertSame('Anna already works 10:00 to 16:00 on Wed 5 Feb', $violation->reason);
    }

    public function testShiftsThatOnlyTouchDoNotOverlap(): void
    {
        $existing = $this->shift('2031-02-05', '10:00', '16:00');

        self::assertNull($this->rule([$existing])->check($this->employee(), $this->shift('2031-02-05', '16:00', '20:00')));
        self::assertNull($this->rule([$existing])->check($this->employee(), $this->shift('2031-02-05', '06:00', '10:00')));
    }

    public function testAShiftInsideAnotherOverlaps(): void
    {
        $existing = $this->shift('2031-02-05', '08:00', '20:00');

        self::assertNotNull($this->rule([$existing])->check($this->employee(), $this->shift('2031-02-05', '12:00', '13:00')));
    }

    public function testAnOvernightShiftOverlapsTheNextMorning(): void
    {
        // Tuesday 22:00 to Wednesday 06:00.
        $existing = $this->shift('2031-02-04', '22:00', '06:00');

        self::assertNotNull($this->rule([$existing])->check($this->employee(), $this->shift('2031-02-05', '05:00', '09:00')));
        self::assertNull($this->rule([$existing])->check($this->employee(), $this->shift('2031-02-05', '06:00', '10:00')));
    }

    public function testAShiftOnAnotherDayDoesNotOverlap(): void
    {
        $existing = $this->shift('2031-02-04', '10:00', '16:00');

        self::assertNull($this->rule([$existing])->check($this->employee(), $this->shift('2031-02-05', '10:00', '16:00')));
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

    public function testLooksOneDayBeforeAndOneDayAfterTheShift(): void
    {
        $assignments = $this->createMock(AssignmentRepository::class);
        $employee = $this->employee();
        $assignments->expects(self::once())
            ->method('findShiftsOfEmployeeBetweenDates')
            ->with(
                $employee,
                self::callback(static fn (\DateTimeImmutable $from): bool => '2031-02-04' === $from->format('Y-m-d')),
                self::callback(static fn (\DateTimeImmutable $to): bool => '2031-02-06' === $to->format('Y-m-d')),
            )
            ->willReturn([]);

        (new OverlapRule($assignments))->check($employee, $this->shift('2031-02-05', '10:00', '16:00'));
    }

    /** @param list<Shift> $employeesShifts */
    private function rule(array $employeesShifts): OverlapRule
    {
        $assignments = $this->createStub(AssignmentRepository::class);
        $assignments->method('findShiftsOfEmployeeBetweenDates')->willReturn($employeesShifts);

        return new OverlapRule($assignments);
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
