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
use App\Rule\PositionMatchRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PositionMatchRuleTest extends TestCase
{
    /** @return iterable<string, array{Position, Position}> every employee position against every shift position */
    public static function allCombinations(): iterable
    {
        foreach (Position::cases() as $employeePosition) {
            foreach (Position::cases() as $shiftPosition) {
                yield $employeePosition->value.' on a '.$shiftPosition->value.' shift' => [$employeePosition, $shiftPosition];
            }
        }
    }

    #[DataProvider('allCombinations')]
    public function testOnlyAnExactMatchIsAccepted(Position $employeePosition, Position $shiftPosition): void
    {
        $violation = (new PositionMatchRule())->check($this->employee($employeePosition), $this->shift($shiftPosition));

        if ($employeePosition === $shiftPosition) {
            self::assertNull($violation);

            return;
        }

        self::assertNotNull($violation);
        self::assertSame('POSITION_MISMATCH', $violation->code);
    }

    public function testTheReasonNamesBothPositions(): void
    {
        $violation = (new PositionMatchRule())->check($this->employee(Position::Kitchen), $this->shift(Position::Barista));

        self::assertNotNull($violation);
        self::assertSame('Shift needs barista, Anna is kitchen', $violation->reason);
    }

    public function testMultiWordPositionsReadNaturally(): void
    {
        $violation = (new PositionMatchRule())->check($this->employee(Position::Barista), $this->shift(Position::ShiftLead));

        self::assertNotNull($violation);
        self::assertSame('Shift needs shift lead, Anna is barista', $violation->reason);
    }

    private function employee(Position $position): Employee
    {
        return new Employee(
            new User('anna@example.com', 'hash', UserRole::Employee),
            'Anna',
            $position,
            ContractType::PartTime,
            20,
        );
    }

    private function shift(Position $position): Shift
    {
        return new Shift(
            new Roster(new \DateTimeImmutable('2031-02-03')),
            new \DateTimeImmutable('2031-02-05'),
            new \DateTimeImmutable('09:00'),
            new \DateTimeImmutable('15:00'),
            $position,
            1,
        );
    }
}
