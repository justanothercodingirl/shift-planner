<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Employee;
use App\Enum\Position;

/** The one place that decides what an employee looks like in API responses. */
final class EmployeeResponse
{
    /**
     * @return array{id: int|null, name: string, email: string, position: string, additionalPositions: list<string>, contractType: string, maxWeeklyHours: int, active: bool}
     */
    public static function from(Employee $employee): array
    {
        return [
            'id' => $employee->getId(),
            'name' => $employee->getName(),
            'email' => $employee->getUser()->getEmail(),
            'position' => $employee->getPosition()->value,
            'additionalPositions' => array_map(
                static fn (Position $position): string => $position->value,
                $employee->getAdditionalPositions(),
            ),
            'contractType' => $employee->getContractType()->value,
            'maxWeeklyHours' => $employee->getMaxWeeklyHours(),
            'active' => $employee->isActive(),
        ];
    }
}
