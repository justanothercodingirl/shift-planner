<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Availability;
use App\Entity\Employee;
use App\Entity\User;
use App\Enum\AvailabilityType;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Enum\Weekday;
use PHPUnit\Framework\TestCase;

final class AvailabilityTest extends TestCase
{
    public function testAWeeklyEntryAppliesOnEveryDateWithThatWeekday(): void
    {
        $monday = $this->weekly(Weekday::Monday, '08:00', '16:00');

        self::assertTrue($monday->appliesOn(new \DateTimeImmutable('2031-02-03')));
        self::assertTrue($monday->appliesOn(new \DateTimeImmutable('2031-02-10')));
        self::assertFalse($monday->appliesOn(new \DateTimeImmutable('2031-02-04')));
    }

    public function testAOneOffEntryAppliesOnlyOnItsDate(): void
    {
        $oneOff = $this->oneOff('2031-02-05', '08:00', '16:00');

        self::assertTrue($oneOff->appliesOn(new \DateTimeImmutable('2031-02-05')));
        self::assertFalse($oneOff->appliesOn(new \DateTimeImmutable('2031-02-12')));
    }

    public function testTheWindowIsPlacedOnTheGivenDate(): void
    {
        $monday = $this->weekly(Weekday::Monday, '08:00', '16:30');
        $date = new \DateTimeImmutable('2031-02-10');

        self::assertSame('2031-02-10 08:00', $monday->getStartOn($date)->format('Y-m-d H:i'));
        self::assertSame('2031-02-10 16:30', $monday->getEndOn($date)->format('Y-m-d H:i'));
    }

    public function testWindowsOverlapWhenEachStartsBeforeTheOtherEnds(): void
    {
        $a = $this->weekly(Weekday::Monday, '08:00', '12:00');

        self::assertTrue($a->overlapsInTime($this->weekly(Weekday::Monday, '11:00', '14:00')));
        self::assertTrue($a->overlapsInTime($this->weekly(Weekday::Monday, '09:00', '10:00')));
        self::assertTrue($a->overlapsInTime($this->weekly(Weekday::Monday, '06:00', '20:00')));
    }

    public function testWindowsThatOnlyTouchDoNotOverlap(): void
    {
        $a = $this->weekly(Weekday::Monday, '08:00', '12:00');

        self::assertFalse($a->overlapsInTime($this->weekly(Weekday::Monday, '12:00', '14:00')));
        self::assertFalse($a->overlapsInTime($this->weekly(Weekday::Monday, '06:00', '08:00')));
    }

    public function testTwoWeeklyEntriesOnTheSameWeekdayAreTheSameKind(): void
    {
        $a = $this->weekly(Weekday::Monday, '08:00', '12:00');

        self::assertTrue($a->isSameKind($this->weekly(Weekday::Monday, '13:00', '14:00')));
        self::assertFalse($a->isSameKind($this->weekly(Weekday::Tuesday, '08:00', '12:00')));
    }

    public function testTwoOneOffEntriesOnTheSameDateAreTheSameKind(): void
    {
        $a = $this->oneOff('2031-02-05', '08:00', '12:00');

        self::assertTrue($a->isSameKind($this->oneOff('2031-02-05', '13:00', '14:00')));
        self::assertFalse($a->isSameKind($this->oneOff('2031-02-06', '08:00', '12:00')));
    }

    public function testAWeeklyAndAOneOffEntryAreNeverTheSameKind(): void
    {
        $weekly = $this->weekly(Weekday::Wednesday, '08:00', '12:00');

        self::assertFalse($weekly->isSameKind($this->oneOff('2031-02-05', '08:00', '12:00')));
    }

    public function testUpdateReplacesTheFields(): void
    {
        $entry = $this->weekly(Weekday::Monday, '08:00', '12:00');

        $entry->update(null, new \DateTimeImmutable('2031-02-05'), new \DateTimeImmutable('09:00'), new \DateTimeImmutable('10:00'), AvailabilityType::Preferred);

        self::assertNull($entry->getWeekday());
        self::assertSame('2031-02-05', $entry->getDate()?->format('Y-m-d'));
        self::assertSame('09:00', $entry->getStartTime()->format('H:i'));
        self::assertSame('10:00', $entry->getEndTime()->format('H:i'));
        self::assertSame(AvailabilityType::Preferred, $entry->getType());
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
}
