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
}
