<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Assignment;

/** One of the signed-in employee's own shifts, as the "My shifts" screen needs it. */
final class MyAssignmentResponse
{
    /**
     * @return array{id: int|null, shiftId: int|null, weekStart: string, date: string, startTime: string, endTime: string, requiredPosition: string}
     */
    public static function from(Assignment $assignment): array
    {
        $shift = $assignment->getShift();

        return [
            'id' => $assignment->getId(),
            'shiftId' => $shift->getId(),
            'weekStart' => $shift->getRoster()->getWeekStart()->format('Y-m-d'),
            'date' => $shift->getDate()->format('Y-m-d'),
            'startTime' => $shift->getStartTime()->format('H:i'),
            'endTime' => $shift->getEndTime()->format('H:i'),
            'requiredPosition' => $shift->getRequiredPosition()->value,
        ];
    }
}
