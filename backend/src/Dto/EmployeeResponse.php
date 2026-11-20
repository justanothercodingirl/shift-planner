<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Employee;

/** The one place that decides what an employee looks like in API responses. */
final class EmployeeResponse
{
    /**
     * @return array{id: int|null, name: string, email: string, position: string, contractType: string, maxWeeklyHours: int, active: bool}
     */
    public static function from(Employee $employee): array
    {
        return [
            'id' => $employee->getId(),
            'name' => $employee->getName(),
            'email' => $employee->getUser()->getEmail(),
            'position' => $employee->getPosition()->value,
            'contractType' => $employee->getContractType()->value,
            'maxWeeklyHours' => $employee->getMaxWeeklyHours(),
            'active' => $employee->isActive(),
        ];
    }
}
