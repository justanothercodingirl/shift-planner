<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\Position;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PositionTest extends TestCase
{
    /** @return iterable<string, array{Position, string}> */
    public static function labels(): iterable
    {
        yield 'barista' => [Position::Barista, 'barista'];
        yield 'service' => [Position::Service, 'service'];
        yield 'kitchen' => [Position::Kitchen, 'kitchen'];
        yield 'bar' => [Position::Bar, 'bar'];
        yield 'shift lead' => [Position::ShiftLead, 'shift lead'];
    }

    #[DataProvider('labels')]
    public function testLabelIsThePositionInPlainWords(Position $position, string $expected): void
    {
        self::assertSame($expected, $position->label());
    }
}
