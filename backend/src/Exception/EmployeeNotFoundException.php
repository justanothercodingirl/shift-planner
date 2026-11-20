<?php

declare(strict_types=1);

namespace App\Exception;

final class EmployeeNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Employee %d was not found.', $id), 404, 'EMPLOYEE_NOT_FOUND');
    }
}
