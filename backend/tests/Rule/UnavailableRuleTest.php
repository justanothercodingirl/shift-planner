<?php

declare(strict_types=1);

namespace App\Tests\Rule;

use App\Entity\Availability;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\User;
use App\Enum\AvailabilityType;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Enum\Weekday;
use App\Repository\AvailabilityRepository;
use App\Rule\UnavailableRule;
use PHPUnit\Framework\TestCase;

/** 2031-02-05 is a Wednesday. */
final class UnavailableRuleTest extends TestCase
{
    public function testAShiftInsideAWeeklyUnavailableWindowIsReported(): void
    {
        $entries = [$this->weekly(Weekday::Wednesday, '08:00', '16:00')];

        $violation = $this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '10:00', '14:00'));

        self::assertNotNull($violation);
        self::assertSame('UNAVAILABLE', $violation->code);
        self::assertSame('Anna is unavailable Wednesday 08:00 to 16:00', $violation->reason);
    }

    public function testTheSameTimesOnAnotherWeekdayAreFine(): void
    {
        $entries = [$this->weekly(Weekday::Monday, '08:00', '16:00')];

        self::assertNull($this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '10:00', '14:00')));
    }

    public function testAOneOffEntryBlocksOnlyItsDate(): void
    {
        $entries = [$this->oneOff('2031-02-05', '08:00', '16:00')];

        $violation = $this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '10:00', '14:00'));

        self::assertNotNull($violation);
        self::assertSame('Anna is unavailable Wed 5 Feb 08:00 to 16:00', $violation->reason);
        self::assertNull($this->rule($entries)->check($this->employee(), $this->shift('2031-02-12', '10:00', '14:00')));
    }

    public function testAShiftThatOnlyTouchesTheWindowIsFine(): void
    {
        $entries = [$this->weekly(Weekday::Wednesday, '08:00', '12:00')];

        self::assertNull($this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '12:00', '18:00')));
        self::assertNull($this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '04:00', '08:00')));
    }

    public function testAShiftThatStartsBeforeAndEndsInsideTheWindowIsReported(): void
    {
        $entries = [$this->weekly(Weekday::Wednesday, '08:00', '12:00')];

        self::assertNotNull($this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '06:00', '09:00')));
    }

    public function testAShiftThatCoversTheWholeWindowIsReported(): void
    {
        $entries = [$this->weekly(Weekday::Wednesday, '10:00', '11:00')];

        self::assertNotNull($this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '08:00', '16:00')));
    }

    public function testAnOvernightShiftThatRunsIntoTheNextDaysWindowIsReported(): void
    {
        // Wednesday 22:00 to Thursday 06:00 runs into Thursday 02:00 to 04:00.
        $entries = [$this->weekly(Weekday::Thursday, '02:00', '04:00')];

        $violation = $this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '22:00', '06:00'));

        self::assertNotNull($violation);
        self::assertSame('Anna is unavailable Thursday 02:00 to 04:00', $violation->reason);
    }

    public function testAnOvernightShiftDoesNotCheckTheNextDayWhenItEndsBeforeTheWindow(): void
    {
        $entries = [$this->weekly(Weekday::Thursday, '08:00', '16:00')];

        self::assertNull($this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '22:00', '06:00')));
    }

    public function testAShiftOnTheDayAfterAnOvernightWindowDayIsNotAffected(): void
    {
        // The window is Wednesday only; a Thursday shift is not blocked by it.
        $entries = [$this->weekly(Weekday::Wednesday, '08:00', '23:59')];

        self::assertNull($this->rule($entries)->check($this->employee(), $this->shift('2031-02-06', '08:00', '16:00')));
    }

    public function testAWholeDayWindowBlocksAnyShiftThatDay(): void
    {
        $entries = [$this->oneOff('2031-02-05', '00:00', '23:59')];

        self::assertNotNull($this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '07:00', '09:00')));
        self::assertNotNull($this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '20:00', '23:00')));
    }

    public function testNoEntriesMeansAvailable(): void
    {
        self::assertNull($this->rule([])->check($this->employee(), $this->shift('2031-02-05', '10:00', '14:00')));
    }

    public function testOnlyTheFirstClashingWindowIsReported(): void
    {
        $entries = [
            $this->weekly(Weekday::Wednesday, '08:00', '10:00'),
            $this->weekly(Weekday::Wednesday, '12:00', '14:00'),
        ];

        $violation = $this->rule($entries)->check($this->employee(), $this->shift('2031-02-05', '09:00', '13:00'));

        self::assertNotNull($violation);
        self::assertSame('Anna is unavailable Wednesday 08:00 to 10:00', $violation->reason);
    }

    public function testTheRepositoryIsAskedForTheEmployeesUnavailableEntries(): void
    {
        $employee = $this->employee();
        $repository = $this->createMock(AvailabilityRepository::class);
        $repository->expects(self::once())->method('findUnavailableByEmployee')->with($employee)->willReturn([]);

        (new UnavailableRule($repository))->check($employee, $this->shift('2031-02-05', '10:00', '14:00'));
    }

    /** @param list<Availability> $entries */
    private function rule(array $entries): UnavailableRule
    {
        $repository = $this->createStub(AvailabilityRepository::class);
        $repository->method('findUnavailableByEmployee')->willReturn($entries);

        return new UnavailableRule($repository);
    }

    private function weekly(Weekday $weekday, string $from, string $to): Availability
    {
        return new Availability($this->employee(), $weekday, null, new \DateTimeImmutable($from), new \DateTimeImmutable($to), AvailabilityType::Unavailable);
    }

    private function oneOff(string $date, string $from, string $to): Availability
    {
        return new Availability($this->employee(), null, new \DateTimeImmutable($date), new \DateTimeImmutable($from), new \DateTimeImmutable($to), AvailabilityType::Unavailable);
    }

    private function employee(): Employee
    {
        return new Employee(new User('anna@example.com', 'hash', UserRole::Employee), 'Anna', Position::Barista, ContractType::PartTime, 20);
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
