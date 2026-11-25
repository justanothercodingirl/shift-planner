<?php

declare(strict_types=1);

namespace App\Exception;

final class AvailabilityTimesInvalidException extends DomainException
{
    public function __construct(string $startTime, string $endTime)
    {
        parent::__construct(
            sprintf('The availability must end after it starts (got %s to %s).', $startTime, $endTime),
            422,
            'AVAILABILITY_TIMES_INVALID',
        );
    }
}
