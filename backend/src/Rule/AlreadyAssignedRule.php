<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Employee;
use App\Entity\Shift;
use App\Repository\AssignmentRepository;

/** An employee cannot be on the same shift twice. */
final class AlreadyAssignedRule implements ShiftRule
{
    public function __construct(private readonly AssignmentRepository $assignments)
    {
    }

    public function check(Employee $employee, Shift $shift): ?Violation
    {
        if (!$this->assignments->isAssigned($shift, $employee)) {
            return null;
        }

        return new Violation('ALREADY_ASSIGNED', sprintf('%s is already on this shift', $employee->getName()));
    }
}
