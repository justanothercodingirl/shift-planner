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
use App\Rule\AlreadyAssignedRule;
use PHPUnit\Framework\TestCase;

final class AlreadyAssignedRuleTest extends TestCase
{
    public function testReportsAnEmployeeWhoIsAlreadyOnTheShift(): void
    {
        $assignments = $this->createStub(AssignmentRepository::class);
        $assignments->method('isAssigned')->willReturn(true);

        $violation = (new AlreadyAssignedRule($assignments))->check($this->employee(), $this->shift());

        self::assertNotNull($violation);
        self::assertSame('ALREADY_ASSIGNED', $violation->code);
        self::assertSame('Anna is already on this shift', $violation->reason);
    }

    public function testAcceptsAnEmployeeWhoIsNotOnTheShift(): void
    {
        $assignments = $this->createStub(AssignmentRepository::class);
        $assignments->method('isAssigned')->willReturn(false);

        self::assertNull((new AlreadyAssignedRule($assignments))->check($this->employee(), $this->shift()));
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

    private function shift(): Shift
    {
        return new Shift(
            new Roster(new \DateTimeImmutable('2031-02-03')),
            new \DateTimeImmutable('2031-02-05'),
            new \DateTimeImmutable('09:00'),
            new \DateTimeImmutable('15:00'),
            Position::Barista,
            2,
        );
    }
}
