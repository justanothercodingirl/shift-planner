<?php

declare(strict_types=1);

namespace App\Exception;

final class AssignmentNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Assignment %d was not found.', $id), 404, 'ASSIGNMENT_NOT_FOUND');
    }
}
