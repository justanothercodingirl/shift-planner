<?php

declare(strict_types=1);

namespace App\Exception;

final class RosterAlreadyExistsException extends DomainException
{
    public function __construct(string $weekStart)
    {
        parent::__construct(
            sprintf('A roster for the week starting %s already exists.', $weekStart),
            409,
            'ROSTER_ALREADY_EXISTS',
        );
    }
}
