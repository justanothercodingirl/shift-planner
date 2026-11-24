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
use App\Repository\AssignmentRepository;
use App\Rule\ShiftFullRule;
use PHPUnit\Framework\TestCase;

final class ShiftFullRuleTest extends TestCase
{
    public function testReportsAShiftWhoseSlotsAreAllFilled(): void
    {
        $violation = $this->rule(assigned: 2)->check($this->employee(), $this->shift(headcount: 2));

        self::assertNotNull($violation);
        self::assertSame('SHIFT_FULL', $violation->code);
        self::assertSame('All 2 barista slots are already filled', $violation->reason);
    }

    public function testUsesTheSingularForAShiftWithOneSlot(): void
    {
        $violation = $this->rule(assigned: 1)->check($this->employee(), $this->shift(headcount: 1));

        self::assertNotNull($violation);
        self::assertSame('The only barista slot is already filled', $violation->reason);
    }

    public function testNamesTheShiftLeadPositionWithASpace(): void
    {
        $violation = $this->rule(assigned: 1)->check($this->employee(), $this->shift(headcount: 1, position: Position::ShiftLead));

        self::assertNotNull($violation);
        self::assertSame('The only shift lead slot is already filled', $violation->reason);
    }

    public function testAcceptsAShiftThatStillHasAFreeSlot(): void
    {
        self::assertNull($this->rule(assigned: 1)->check($this->employee(), $this->shift(headcount: 2)));
    }

    private function rule(int $assigned): ShiftFullRule
    {
        $assignments = $this->createStub(AssignmentRepository::class);
        $assignments->method('countForShift')->willReturn($assigned);

        return new ShiftFullRule($assignments);
    }

    private function employee(): Employee
    {
        return new Employee(
            new User('anna@example.com', 'hash', UserRole::Employee),
            'Anna',
            Position::Barista,
            ContractType::PartTime,
            20,
        );
    }

    private function shift(int $headcount, Position $position = Position::Barista): Shift
    {
        return new Shift(
            new Roster(new \DateTimeImmutable('2031-02-03')),
            new \DateTimeImmutable('2031-02-05'),
            new \DateTimeImmutable('09:00'),
            new \DateTimeImmutable('15:00'),
            $position,
            $headcount,
        );
    }
}
