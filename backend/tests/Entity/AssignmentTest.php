<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use PHPUnit\Framework\TestCase;

final class AssignmentTest extends TestCase
{
    public function testLinksAShiftAndAnEmployee(): void
    {
        $shift = new Shift(
            new Roster(new \DateTimeImmutable('2031-02-03')),
            new \DateTimeImmutable('2031-02-05'),
            new \DateTimeImmutable('09:00'),
            new \DateTimeImmutable('15:00'),
            Position::Barista,
            2,
        );
        $employee = new Employee(
            new User('anna@example.com', 'hash', UserRole::Employee),
            'Anna',
            Position::Barista,
            ContractType::PartTime,
            20,
        );

        $assignment = new Assignment($shift, $employee);

        self::assertNull($assignment->getId());
        self::assertSame($shift, $assignment->getShift());
        self::assertSame($employee, $assignment->getEmployee());
    }
}
