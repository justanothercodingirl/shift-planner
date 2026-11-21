<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Roster;
use App\Enum\RosterStatus;
use PHPUnit\Framework\TestCase;

final class RosterTest extends TestCase
{
    public function testStartsAsDraftForItsWeek(): void
    {
        $roster = new Roster(new \DateTimeImmutable('2026-11-23'));

        self::assertSame('2026-11-23', $roster->getWeekStart()->format('Y-m-d'));
        self::assertSame(RosterStatus::Draft, $roster->getStatus());
    }

    public function testStatusUsesTheSpecValues(): void
    {
        self::assertSame(
            ['draft', 'published'],
            array_map(static fn (RosterStatus $s): string => $s->value, RosterStatus::cases()),
        );
    }
}
