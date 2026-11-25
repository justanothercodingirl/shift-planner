<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Employee;
use App\Entity\Shift;
use App\Repository\AvailabilityRepository;

/**
 * The shift must not overlap one of the employee's "unavailable" windows. Windows
 * that only touch the shift do not count, and "preferred" windows are never checked.
 * An overnight shift is also checked against the windows of the next day.
 */
final class UnavailableRule implements ShiftRule
{
    public function __construct(private readonly AvailabilityRepository $availabilities)
    {
    }

    public function check(Employee $employee, Shift $shift): ?Violation
    {
        $entries = $this->availabilities->findUnavailableByEmployee($employee);
        $startsAt = $shift->getStartsAt();
        $endsAt = $shift->getEndsAt();

        foreach ($this->daysOf($shift) as $day) {
            foreach ($entries as $entry) {
                if (!$entry->appliesOn($day) || $startsAt >= $entry->getEndOn($day) || $entry->getStartOn($day) >= $endsAt) {
                    continue;
                }

                return new Violation('UNAVAILABLE', sprintf(
                    '%s is unavailable %s %s to %s',
                    $employee->getName(),
                    $entry->getDayLabel(),
                    $entry->getStartTime()->format('H:i'),
                    $entry->getEndTime()->format('H:i'),
                ));
            }
        }

        return null;
    }

    /**
     * The shift's own day, and the next day too when it ends after midnight.
     *
     * @return list<\DateTimeImmutable>
     */
    private function daysOf(Shift $shift): array
    {
        $day = $shift->getDate();
        $endsNextDay = $shift->getEndsAt()->format('Y-m-d') !== $day->format('Y-m-d');

        return $endsNextDay ? [$day, $day->modify('+1 day')] : [$day];
    }
}
