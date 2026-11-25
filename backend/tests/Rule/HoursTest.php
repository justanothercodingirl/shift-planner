<?php

declare(strict_types=1);

namespace App\Tests\Rule;

use App\Rule\Hours;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HoursTest extends TestCase
{
    /** @return iterable<string, array{float, string}> */
    public static function hours(): iterable
    {
        yield 'whole number' => [9.0, '9'];
        yield 'two digits' => [10.0, '10'];
        yield 'three digits' => [100.0, '100'];
        yield 'zero' => [0.0, '0'];
        yield 'half hour' => [9.5, '9.5'];
        yield 'rounded to one decimal' => [8.04, '8'];
        yield 'quarter hour rounds up' => [7.26, '7.3'];
    }

    #[DataProvider('hours')]
    public function testFormatWritesHoursNaturally(float $hours, string $expected): void
    {
        self::assertSame($expected, Hours::format($hours));
    }
}
