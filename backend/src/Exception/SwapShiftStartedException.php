<?php

declare(strict_types=1);

namespace App\Exception;

final class SwapShiftStartedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'The shift has already started or is over, so it can no longer be swapped.',
            422,
            'SWAP_SHIFT_STARTED',
        );
    }
}
