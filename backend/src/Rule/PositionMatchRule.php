<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Employee;
use App\Entity\Shift;

/** The employee's position must match the shift's required position exactly. */
final class PositionMatchRule implements ShiftRule
{
    public function check(Employee $employee, Shift $shift): ?Violation
    {
        if ($employee->getPosition() === $shift->getRequiredPosition()) {
            return null;
        }

        return new Violation('POSITION_MISMATCH', sprintf(
            'Shift needs %s, %s is %s',
            $shift->getRequiredPosition()->label(),
            $employee->getName(),
            $employee->getPosition()->label(),
        ));
    }
}
