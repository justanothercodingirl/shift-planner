<?php

declare(strict_types=1);

namespace App\Exception;

final class InvalidShiftTimesException extends DomainException
{
    public function __construct(string $time)
    {
        parent::__construct(
            sprintf('A shift cannot start and end at the same time (%s).', $time),
            422,
            'SHIFT_TIMES_INVALID',
        );
    }
}
