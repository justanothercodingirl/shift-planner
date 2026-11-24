<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Employee;
use App\Entity\Shift;
use App\Exception\RuleViolationException;
use App\Rule\ShiftRule;
use App\Rule\Violation;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Runs every ShiftRule and reports ALL violations at once, not only the first. */
class ShiftValidator
{
    /** @param iterable<ShiftRule> $rules every service tagged "app.shift_rule" */
    public function __construct(
        #[AutowireIterator('app.shift_rule')]
        private readonly iterable $rules,
    ) {
    }

    /**
     * @return list<Violation> sorted by code, so the answer does not depend on the order rules were found in
     */
    public function validate(Employee $employee, Shift $shift): array
    {
        $violations = [];
        foreach ($this->rules as $rule) {
            $violation = $rule->check($employee, $shift);
            if (null !== $violation) {
                $violations[] = $violation;
            }
        }

        usort($violations, static fn (Violation $a, Violation $b): int => strcmp($a->code, $b->code));

        return $violations;
    }

    /** @throws RuleViolationException when at least one rule is broken */
    public function assertValid(Employee $employee, Shift $shift): void
    {
        $violations = $this->validate($employee, $shift);

        if ([] !== $violations) {
            throw new RuleViolationException($violations);
        }
    }
}
