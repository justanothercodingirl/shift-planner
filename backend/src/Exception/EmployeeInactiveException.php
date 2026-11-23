<?php

declare(strict_types=1);

namespace App\Exception;

final class EmployeeInactiveException extends DomainException
{
    public function __construct(string $name)
    {
        parent::__construct(
            sprintf('%s is deactivated and cannot be assigned to shifts.', $name),
            422,
            'EMPLOYEE_INACTIVE',
        );
    }
}
