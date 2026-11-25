<?php

declare(strict_types=1);

namespace App\Exception;

final class AvailabilityNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Availability %d was not found.', $id), 404, 'AVAILABILITY_NOT_FOUND');
    }
}
