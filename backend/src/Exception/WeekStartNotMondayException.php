<?php

declare(strict_types=1);

namespace App\Exception;

final class WeekStartNotMondayException extends DomainException
{
    public function __construct(string $weekStart)
    {
        parent::__construct(
            sprintf('A roster week must start on a Monday, but %s is not one.', $weekStart),
            422,
            'WEEK_START_NOT_MONDAY',
        );
    }
}
