<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Roster;
use App\Entity\Shift;
use App\Enum\Position;
use PHPUnit\Framework\TestCase;

final class ShiftTest extends TestCase
{
    public function testExposesItsValues(): void
    {
        $roster = new Roster(new \DateTimeImmutable('2026-11-23'));

        $shift = new Shift(
            $roster,
            new \DateTimeImmutable('2026-11-28'),
            new \DateTimeImmutable('08:00'),
            new \DateTimeImmutable('14:00'),
            Position::Barista,
            2,
        );

        self::assertSame($roster, $shift->getRoster());
        self::assertSame('2026-11-28', $shift->getDate()->format('Y-m-d'));
        self::assertSame('08:00', $shift->getStartTime()->format('H:i'));
        self::assertSame('14:00', $shift->getEndTime()->format('H:i'));
        self::assertSame(Position::Barista, $shift->getRequiredPosition());
        self::assertSame(2, $shift->getHeadcount());
    }

    public function testAShiftWithinOneDayStartsAndEndsOnItsDate(): void
    {
        $shift = $this->shift('2031-02-05', '09:00', '15:30');

        self::assertSame('2031-02-05 09:00', $shift->getStartsAt()->format('Y-m-d H:i'));
        self::assertSame('2031-02-05 15:30', $shift->getEndsAt()->format('Y-m-d H:i'));
    }

    public function testAnEndEarlierThanTheStartMeansTheShiftEndsTheNextDay(): void
    {
        $shift = $this->shift('2031-02-07', '18:00', '02:00');

        self::assertSame('2031-02-07 18:00', $shift->getStartsAt()->format('Y-m-d H:i'));
        self::assertSame('2031-02-08 02:00', $shift->getEndsAt()->format('Y-m-d H:i'));
    }

    public function testAnOvernightShiftAtTheEndOfAMonthEndsInTheNextMonth(): void
    {
        $shift = $this->shift('2031-02-28', '22:00', '06:00');

        self::assertSame('2031-03-01 06:00', $shift->getEndsAt()->format('Y-m-d H:i'));
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
