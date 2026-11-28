<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Assignment;
use App\Exception\AssignmentNotFoundException;
use App\Exception\EmployeeInactiveException;
use App\Repository\AssignmentRepository;

class AssignmentService
{
    public function __construct(
        private readonly AssignmentRepository $assignments,
        private readonly ShiftService $shifts,
        private readonly EmployeeService $employees,
        private readonly ShiftValidator $validator,
        private readonly RosterChangeNotifier $notifier,
    ) {
    }

    public function assign(int $shiftId, int $employeeId): Assignment
    {
        $shift = $this->shifts->get($shiftId);
        $employee = $this->employees->get($employeeId);

        if (!$employee->isActive()) {
            throw new EmployeeInactiveException($employee->getName());
        }

        // Runs every ShiftRule and throws a 422 listing all violations at once.
        $this->validator->assertValid($employee, $shift);

        $assignment = new Assignment($shift, $employee);
        $this->assignments->save($assignment);
        $this->notifier->assigned($assignment);

        return $assignment;
    }

    public function unassign(int $id): void
    {
        $assignment = $this->assignments->find($id) ?? throw new AssignmentNotFoundException($id);
        $this->assignments->remove($assignment);
        $this->notifier->unassigned($assignment);
    }
}
