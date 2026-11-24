<?php
declare(strict_types=1);
namespace App\Rule;
use App\Entity\Employee;
use App\Entity\Shift;
final class TryMeRule implements ShiftRule
{
    public function check(Employee $employee, Shift $shift): ?Violation { return null; }
}
