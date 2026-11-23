<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Assignment;
use App\Entity\Shift;
use App\Exception\AssignmentNotFoundException;
use App\Exception\EmployeeInactiveException;
use App\Exception\RuleViolationException;
use App\Repository\AssignmentRepository;
use App\Rule\Violation;

class AssignmentService
{
    public function __construct(
        private readonly AssignmentRepository $assignments,
        private readonly ShiftService $shifts,
        private readonly EmployeeService $employees,
    ) {
    }

    /**
     * ALREADY_ASSIGNED and SHIFT_FULL are checked here for now; they move into
     * ShiftRule classes when the rule engine arrives (task 15).
     */
    public function assign(int $shiftId, int $employeeId): Assignment
    {
        $shift = $this->shifts->get($shiftId);
        $employee = $this->employees->get($employeeId);

        if (!$employee->isActive()) {
            throw new EmployeeInactiveException($employee->getName());
        }

        $violations = [];
        if ($this->assignments->isAssigned($shift, $employee)) {
            $violations[] = new Violation('ALREADY_ASSIGNED', sprintf('%s is already on this shift', $employee->getName()));
        }
        if ($this->assignments->countForShift($shift) >= $shift->getHeadcount()) {
            $violations[] = new Violation('SHIFT_FULL', $this->fullReason($shift));
        }
        if ([] !== $violations) {
            throw new RuleViolationException($violations);
        }

        $assignment = new Assignment($shift, $employee);
        $this->assignments->save($assignment);

        return $assignment;
    }

    public function unassign(int $id): void
    {
        $assignment = $this->assignments->find($id) ?? throw new AssignmentNotFoundException($id);
        $this->assignments->remove($assignment);
    }

    private function fullReason(Shift $shift): string
    {
        $position = str_replace('_', ' ', $shift->getRequiredPosition()->value);

        return 1 === $shift->getHeadcount()
            ? sprintf('The only %s slot is already filled', $position)
            : sprintf('All %d %s slots are already filled', $shift->getHeadcount(), $position);
    }
}
