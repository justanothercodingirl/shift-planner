<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Employee;
use App\Entity\Shift;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One roster rule. Adding a rule means adding one class that implements this
 * interface, plus one test: the tag below registers it with the ShiftValidator
 * automatically, so nothing else has to change.
 */
#[AutoconfigureTag('app.shift_rule')]
interface ShiftRule
{
    /** Returns the violation when assigning $employee to $shift breaks this rule, otherwise null. */
    public function check(Employee $employee, Shift $shift): ?Violation;
}
