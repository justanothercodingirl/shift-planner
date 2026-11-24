<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Employee;
use App\Entity\Shift;
use App\Repository\AssignmentRepository;

/** An employee cannot be in two shifts at once. Shifts that only touch (14:00 end, 14:00 start) do not overlap. */
final class OverlapRule implements ShiftRule
{
    public function __construct(private readonly AssignmentRepository $assignments)
    {
    }

    public function check(Employee $employee, Shift $shift): ?Violation
    {
        // One day either side catches an overnight shift that spills over midnight.
        $day = $shift->getDate();
        $others = $this->assignments->findShiftsOfEmployeeBetweenDates(
            $employee,
            $day->modify('-1 day'),
            $day->modify('+1 day'),
        );

        foreach ($others as $other) {
            if ($this->isSameShift($other, $shift) || !$this->overlaps($other, $shift)) {
                continue;
            }

            return new Violation('OVERLAP', sprintf(
                '%s already works %s to %s on %s',
                $employee->getName(),
                $other->getStartTime()->format('H:i'),
                $other->getEndTime()->format('H:i'),
                $other->getDate()->format('D j M'),
            ));
        }

        return null;
    }

    /** Two shifts overlap when each one starts before the other ends. */
    private function overlaps(Shift $a, Shift $b): bool
    {
        return $a->getStartsAt() < $b->getEndsAt() && $b->getStartsAt() < $a->getEndsAt();
    }

    private function isSameShift(Shift $a, Shift $b): bool
    {
        return $a === $b || (null !== $a->getId() && $a->getId() === $b->getId());
    }
}
