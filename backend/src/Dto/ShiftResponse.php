<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Shift;

/** The one place that decides what a shift looks like in API responses. */
final class ShiftResponse
{
    /**
     * @return array{id: int|null, rosterId: int|null, date: string, startTime: string, endTime: string, requiredPosition: string, headcount: int}
     */
    public static function from(Shift $shift): array
    {
        return [
            'id' => $shift->getId(),
            'rosterId' => $shift->getRoster()->getId(),
            'date' => $shift->getDate()->format('Y-m-d'),
            'startTime' => $shift->getStartTime()->format('H:i'),
            'endTime' => $shift->getEndTime()->format('H:i'),
            'requiredPosition' => $shift->getRequiredPosition()->value,
            'headcount' => $shift->getHeadcount(),
        ];
    }
}
