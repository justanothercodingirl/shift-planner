<?php

declare(strict_types=1);

namespace App\Exception;

final class RosterNotPublishedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Only shifts of a published roster can be swapped.', 422, 'ROSTER_NOT_PUBLISHED');
    }
}
