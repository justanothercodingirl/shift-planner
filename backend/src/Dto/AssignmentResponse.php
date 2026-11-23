<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Assignment;

/** The one place that decides what an assignment looks like in API responses. */
final class AssignmentResponse
{
    /** @return array{id: int|null, shiftId: int|null, employeeId: int|null} */
    public static function from(Assignment $assignment): array
    {
        return [
            'id' => $assignment->getId(),
            'shiftId' => $assignment->getShift()->getId(),
            'employeeId' => $assignment->getEmployee()->getId(),
        ];
    }

    /**
     * The short form shown inside a shift.
     *
     * @return array{id: int|null, employeeId: int|null, employeeName: string}
     */
    public static function summary(Assignment $assignment): array
    {
        return [
            'id' => $assignment->getId(),
            'employeeId' => $assignment->getEmployee()->getId(),
            'employeeName' => $assignment->getEmployee()->getName(),
        ];
    }
}
