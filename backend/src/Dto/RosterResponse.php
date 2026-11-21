<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Roster;
use App\Entity\Shift;

/** The one place that decides what a roster looks like in API responses. */
final class RosterResponse
{
    /**
     * @return array{id: int|null, weekStart: string, status: string}
     */
    public static function from(Roster $roster): array
    {
        return [
            'id' => $roster->getId(),
            'weekStart' => $roster->getWeekStart()->format('Y-m-d'),
            'status' => $roster->getStatus()->value,
        ];
    }

    /**
     * @param list<Shift> $shifts
     *
     * @return array{id: int|null, weekStart: string, status: string, shifts: list<array<string, mixed>>}
     */
    public static function withShifts(Roster $roster, array $shifts): array
    {
        return self::from($roster) + ['shifts' => array_map(ShiftResponse::from(...), $shifts)];
    }
}
