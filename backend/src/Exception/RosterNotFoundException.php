<?php

declare(strict_types=1);

namespace App\Exception;

final class RosterNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Roster %d was not found.', $id), 404, 'ROSTER_NOT_FOUND');
    }
}
