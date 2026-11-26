<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Employee;
use App\Entity\Shift;
use App\Enum\Position;

/**
 * The shift's required position must be the employee's main position or one of
 * their additional positions (for example a barista who can also work service).
 */
final class PositionMatchRule implements ShiftRule
{
    public function check(Employee $employee, Shift $shift): ?Violation
    {
        if ($employee->canWorkAs($shift->getRequiredPosition())) {
            return null;
        }

        return new Violation('POSITION_MISMATCH', sprintf(
            'Shift needs %s, %s is %s',
            $shift->getRequiredPosition()->label(),
            $employee->getName(),
            $this->listOf($employee),
        ));
    }

    /** "kitchen", "barista or service", "barista, service or bar". */
    private function listOf(Employee $employee): string
    {
        $labels = array_map(static fn (Position $position): string => $position->label(), $employee->getPositions());
        $last = array_pop($labels);

        return [] === $labels ? $last : implode(', ', $labels).' or '.$last;
    }
}
