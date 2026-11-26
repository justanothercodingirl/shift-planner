<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Employee;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use PHPUnit\Framework\TestCase;

final class EmployeeTest extends TestCase
{
    public function testExposesItsValues(): void
    {
        $user = new User('anna@example.com', 'hash', UserRole::Employee);

        $employee = new Employee($user, 'Anna', Position::Barista, ContractType::PartTime, 20);

        self::assertSame($user, $employee->getUser());
        self::assertSame('Anna', $employee->getName());
        self::assertSame(Position::Barista, $employee->getPosition());
        self::assertSame(ContractType::PartTime, $employee->getContractType());
        self::assertSame(20, $employee->getMaxWeeklyHours());
    }

    public function testIsActiveByDefault(): void
    {
        $employee = new Employee(
            new User('ben@example.com', 'hash', UserRole::Employee),
            'Ben',
            Position::Service,
            ContractType::WorkingStudent,
            20,
        );

        self::assertTrue($employee->isActive());
    }

    public function testHasNoAdditionalPositionsByDefault(): void
    {
        $employee = $this->employee(Position::Barista);

        self::assertSame([], $employee->getAdditionalPositions());
        self::assertSame([Position::Barista], $employee->getPositions());
    }

    public function testListsTheMainPositionFirstThenTheAdditionalOnes(): void
    {
        $employee = $this->employee(Position::Barista, [Position::Service, Position::Bar]);

        self::assertSame([Position::Service, Position::Bar], $employee->getAdditionalPositions());
        self::assertSame([Position::Barista, Position::Service, Position::Bar], $employee->getPositions());
    }

    public function testCanWorkAsTheMainAndTheAdditionalPositionsOnly(): void
    {
        $anna = $this->employee(Position::Barista, [Position::Service]);

        self::assertTrue($anna->canWorkAs(Position::Barista));
        self::assertTrue($anna->canWorkAs(Position::Service));
        self::assertFalse($anna->canWorkAs(Position::Kitchen));
    }

    public function testDuplicatesAndTheMainPositionAreDroppedFromTheAdditionalOnes(): void
    {
        $employee = $this->employee(Position::Barista, [Position::Service, Position::Barista, Position::Service, Position::Bar]);

        self::assertSame([Position::Service, Position::Bar], $employee->getAdditionalPositions());
    }

    public function testUpdateReplacesTheAdditionalPositionsAndCleansThem(): void
    {
        $employee = $this->employee(Position::Barista, [Position::Service]);

        $employee->update('Anna', Position::Service, ContractType::PartTime, 20, [Position::Service, Position::Kitchen]);

        self::assertSame(Position::Service, $employee->getPosition());
        self::assertSame([Position::Kitchen], $employee->getAdditionalPositions());
    }

    public function testUpdateWithoutAdditionalPositionsClearsThem(): void
    {
        $employee = $this->employee(Position::Barista, [Position::Service]);

        $employee->update('Anna', Position::Barista, ContractType::PartTime, 20);

        self::assertSame([], $employee->getAdditionalPositions());
    }

    public function testEnumsUseTheSpecValues(): void
    {
        self::assertSame(
            ['barista', 'service', 'kitchen', 'bar', 'shift_lead'],
            array_map(static fn (Position $p): string => $p->value, Position::cases()),
        );
        self::assertSame(
            ['full_time', 'part_time', 'working_student', 'mini_job'],
            array_map(static fn (ContractType $c): string => $c->value, ContractType::cases()),
        );
    }

    /** @param list<Position> $additionalPositions */
    private function employee(Position $position, array $additionalPositions = []): Employee
    {
        return new Employee(
            new User('anna@example.com', 'hash', UserRole::Employee),
            'Anna',
            $position,
            ContractType::PartTime,
            20,
            $additionalPositions,
        );
    }
}
