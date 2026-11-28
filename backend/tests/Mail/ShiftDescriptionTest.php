<?php

declare(strict_types=1);

namespace App\Tests\Mail;

use App\Entity\Roster;
use App\Entity\Shift;
use App\Enum\Position;
use App\Mail\ShiftDescription;
use PHPUnit\Framework\TestCase;

final class ShiftDescriptionTest extends TestCase
{
    public function testDescribesDayTimesAndPosition(): void
    {
        self::assertSame('Mon 3 Feb 08:00 to 14:00 (barista)', ShiftDescription::of($this->shift('2031-02-03', '08:00', '14:00', Position::Barista)));
    }

    public function testAnOvernightShiftIsMarked(): void
    {
        self::assertSame('Mon 3 Feb 18:00 to 02:00 +1 day (service)', ShiftDescription::of($this->shift('2031-02-03', '18:00', '02:00', Position::Service)));
    }

    public function testMultiWordPositionsReadNaturally(): void
    {
        self::assertSame('Wed 5 Feb 10:00 to 16:00 (shift lead)', ShiftDescription::of($this->shift('2031-02-05', '10:00', '16:00', Position::ShiftLead)));
    }

    private function shift(string $date, string $start, string $end, Position $position): Shift
    {
        return new Shift(
            new Roster(new \DateTimeImmutable('2031-02-03')),
            new \DateTimeImmutable($date),
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            $position,
            1,
        );
    }
}
