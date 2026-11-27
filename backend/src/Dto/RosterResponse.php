<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Assignment;
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
     * The roster with its shifts, plus the warnings that came up while publishing.
     *
     * @param list<Shift>                               $shifts
     * @param array<int, list<Assignment>>              $assignmentsByShift
     * @param list<array{code: string, reason: string}> $warnings
     *
     * @return array<string, mixed>
     */
    public static function withWarnings(Roster $roster, array $shifts, array $assignmentsByShift, array $warnings): array
    {
        return self::withShifts($roster, $shifts, $assignmentsByShift) + ['warnings' => $warnings];
    }

    /**
     * @param list<Shift>                  $shifts
     * @param array<int, list<Assignment>> $assignmentsByShift assignments keyed by shift id
     *
     * @return array{id: int|null, weekStart: string, status: string, shifts: list<array<string, mixed>>}
     */
    public static function withShifts(Roster $roster, array $shifts, array $assignmentsByShift = []): array
    {
        return self::from($roster) + ['shifts' => array_map(
            static fn (Shift $shift): array => ShiftResponse::from($shift, $assignmentsByShift[$shift->getId()] ?? []),
            $shifts,
        )];
    }
}
