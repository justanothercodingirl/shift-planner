<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\Weekday;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeekdayTest extends TestCase
{
    /** @return iterable<string, array{string, Weekday}> */
    public static function datesOfOneWeek(): iterable
    {
        yield 'Monday' => ['2031-02-03', Weekday::Monday];
        yield 'Tuesday' => ['2031-02-04', Weekday::Tuesday];
        yield 'Wednesday' => ['2031-02-05', Weekday::Wednesday];
        yield 'Thursday' => ['2031-02-06', Weekday::Thursday];
        yield 'Friday' => ['2031-02-07', Weekday::Friday];
        yield 'Saturday' => ['2031-02-08', Weekday::Saturday];
        yield 'Sunday' => ['2031-02-09', Weekday::Sunday];
    }

    #[DataProvider('datesOfOneWeek')]
    public function testFromDateGivesTheWeekdayOfADate(string $date, Weekday $expected): void
    {
        self::assertSame($expected, Weekday::fromDate(new \DateTimeImmutable($date)));
    }

    public function testValuesAreLowercaseEnglishNames(): void
    {
        self::assertSame(
            ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            array_map(static fn (Weekday $day): string => $day->value, Weekday::cases()),
        );
    }

    public function testLabelIsCapitalised(): void
    {
        self::assertSame('Monday', Weekday::Monday->label());
        self::assertSame('Sunday', Weekday::Sunday->label());
    }

    public function testIsoNumberCountsFromMondayAsOne(): void
    {
        self::assertSame(1, Weekday::Monday->isoNumber());
        self::assertSame(7, Weekday::Sunday->isoNumber());
    }
}
