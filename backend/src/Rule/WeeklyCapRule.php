<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Employee;
use App\Entity\Shift;
use App\Repository\AssignmentRepository;

/**
 * Planned hours in a week must not exceed the employee's maxWeeklyHours. A shift
 * counts towards the Monday to Sunday week of its start date, in full, even when
 * it ends after midnight.
 */
final class WeeklyCapRule implements ShiftRule
{
    public function __construct(private readonly AssignmentRepository $assignments)
    {
    }

    public function check(Employee $employee, Shift $shift): ?Violation
    {
        $monday = $this->mondayOf($shift->getDate());
        $others = $this->assignments->findShiftsOfEmployeeBetweenDates(
            $employee,
            $monday,
            $monday->modify('+6 days'),
        );

        $minutes = $shift->getDurationInMinutes();
        foreach ($others as $other) {
            if (!$this->isSameShift($other, $shift)) {
                $minutes += $other->getDurationInMinutes();
            }
        }

        if ($minutes <= $employee->getMaxWeeklyHours() * 60) {
            return null;
        }

        return new Violation('WEEKLY_CAP', sprintf(
            'This would bring %s to %s of %d hours',
            $employee->getName(),
            Hours::format($minutes / 60),
            $employee->getMaxWeeklyHours(),
        ));
    }

    /** The Monday of the week a date belongs to (weeks run Monday to Sunday). */
    private function mondayOf(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify(sprintf('-%d days', (int) $date->format('N') - 1));
    }

    private function isSameShift(Shift $a, Shift $b): bool
    {
        return $a === $b || (null !== $a->getId() && $a->getId() === $b->getId());
    }
}
