<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Employee;
use App\Entity\Shift;
use App\Repository\AssignmentRepository;

/** A shift takes at most "headcount" people. */
final class ShiftFullRule implements ShiftRule
{
    public function __construct(private readonly AssignmentRepository $assignments)
    {
    }

    public function check(Employee $employee, Shift $shift): ?Violation
    {
        if ($this->assignments->countForShift($shift) < $shift->getHeadcount()) {
            return null;
        }

        $position = str_replace('_', ' ', $shift->getRequiredPosition()->value);

        return new Violation(
            'SHIFT_FULL',
            1 === $shift->getHeadcount()
                ? sprintf('The only %s slot is already filled', $position)
                : sprintf('All %d %s slots are already filled', $shift->getHeadcount(), $position),
        );
    }
}
