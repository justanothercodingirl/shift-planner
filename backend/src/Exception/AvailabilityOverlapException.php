<?php

declare(strict_types=1);

namespace App\Exception;

final class AvailabilityOverlapException extends DomainException
{
    /** @param string $existing the clashing entry in words, for example "Monday 08:00 to 12:00" */
    public function __construct(string $existing)
    {
        parent::__construct(
            sprintf('This overlaps an availability you already entered: %s.', $existing),
            422,
            'AVAILABILITY_OVERLAP',
        );
    }
}
