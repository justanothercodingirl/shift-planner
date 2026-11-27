<?php

declare(strict_types=1);

namespace App\Exception;

final class RosterAlreadyPublishedException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct(
            sprintf('Roster %d is already published.', $id),
            409,
            'ROSTER_ALREADY_PUBLISHED',
        );
    }
}
