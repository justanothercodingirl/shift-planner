<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Employee;
use App\Entity\Shift;
use App\Repository\AssignmentRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * An employee needs a minimum number of hours of rest between two shifts, in
 * either direction, across roster weeks. The minimum is the parameter
 * app.rest_period_hours (default 11). Overlapping shifts are reported by OverlapRule.
 */
final class RestPeriodRule implements ShiftRule
{
    public function __construct(
        private readonly AssignmentRepository $assignments,
        #[Autowire('%app.rest_period_hours%')]
        private readonly float $minimumRestHours,
    ) {
    }

    public function check(Employee $employee, Shift $shift): ?Violation
    {
        // Look far enough around the shift for the configured minimum: 2 days for 11 hours.
        $days = (int) ceil($this->minimumRestHours / 24) + 1;
        $day = $shift->getDate();
        $others = $this->assignments->findShiftsOfEmployeeBetweenDates(
            $employee,
            $day->modify(sprintf('-%d days', $days)),
            $day->modify(sprintf('+%d days', $days)),
        );

        $shortest = null;
        foreach ($others as $other) {
            if ($this->isSameShift($other, $shift)) {
                continue;
            }
            $rest = $this->restBetween($other, $shift);
            if (null === $rest || $rest['hours'] >= $this->minimumRestHours) {
                continue;
            }
            if (null === $shortest || $rest['hours'] < $shortest['hours']) {
                $shortest = $rest;
            }
        }

        return null === $shortest ? null : $this->violation($shortest);
    }

    /**
     * The rest between an existing shift and the new one: hours between the end of
     * the earlier shift and the start of the later one, and whether the existing
     * shift comes first. Null when the two overlap, because that is OverlapRule's job.
     *
     * @return array{hours: float, previous: bool}|null
     */
    private function restBetween(Shift $existing, Shift $new): ?array
    {
        if ($existing->getEndsAt() <= $new->getStartsAt()) {
            return ['hours' => $this->hoursBetween($existing->getEndsAt(), $new->getStartsAt()), 'previous' => true];
        }
        if ($new->getEndsAt() <= $existing->getStartsAt()) {
            return ['hours' => $this->hoursBetween($new->getEndsAt(), $existing->getStartsAt()), 'previous' => false];
        }

        return null;
    }

    private function hoursBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        return ($to->getTimestamp() - $from->getTimestamp()) / 3600;
    }

    /** @param array{hours: float, previous: bool} $rest */
    private function violation(array $rest): Violation
    {
        $hours = Hours::format($rest['hours']);

        return new Violation('REST_PERIOD', sprintf(
            'Only %s %s rest %s',
            $hours,
            '1' === $hours ? 'hour' : 'hours',
            $rest['previous'] ? 'since the previous shift' : 'before the next shift',
        ));
    }

    private function isSameShift(Shift $a, Shift $b): bool
    {
        return $a === $b || (null !== $a->getId() && $a->getId() === $b->getId());
    }
}
