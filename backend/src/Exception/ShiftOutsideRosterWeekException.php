<?php

declare(strict_types=1);

namespace App\Exception;

final class ShiftOutsideRosterWeekException extends DomainException
{
    public function __construct(string $date, string $weekStart, string $weekEnd)
    {
        parent::__construct(
            sprintf('The shift date %s is outside the roster week %s to %s.', $date, $weekStart, $weekEnd),
            422,
            'SHIFT_OUTSIDE_ROSTER_WEEK',
        );
    }
}
