<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Employee;
use App\Enum\Position;

/** What an employee may know about a colleague: name and positions, no contact details. */
final class ColleagueResponse
{
    /**
     * @return array{id: int|null, name: string, position: string, additionalPositions: list<string>}
     */
    public static function from(Employee $employee): array
    {
        return [
            'id' => $employee->getId(),
            'name' => $employee->getName(),
            'position' => $employee->getPosition()->value,
            'additionalPositions' => array_map(
                static fn (Position $position): string => $position->value,
                $employee->getAdditionalPositions(),
            ),
        ];
    }
}
