<?php

declare(strict_types=1);

namespace App\Exception;

final class RosterHasNoShiftsException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct(
            sprintf('Roster %d has no shifts, so there is nothing to publish.', $id),
            422,
            'ROSTER_HAS_NO_SHIFTS',
        );
    }
}
