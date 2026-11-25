<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Availability;

/** The one place that decides what an availability entry looks like in API responses. */
final class AvailabilityResponse
{
    /**
     * @return array{id: int|null, employeeId: int|null, weekday: string|null, date: string|null, startTime: string, endTime: string, type: string}
     */
    public static function from(Availability $availability): array
    {
        return [
            'id' => $availability->getId(),
            'employeeId' => $availability->getEmployee()->getId(),
            'weekday' => $availability->getWeekday()?->value,
            'date' => $availability->getDate()?->format('Y-m-d'),
            'startTime' => $availability->getStartTime()->format('H:i'),
            'endTime' => $availability->getEndTime()->format('H:i'),
            'type' => $availability->getType()->value,
        ];
    }
}
