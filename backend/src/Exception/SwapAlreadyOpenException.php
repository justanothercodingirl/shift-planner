<?php

declare(strict_types=1);

namespace App\Exception;

final class SwapAlreadyOpenException extends DomainException
{
    public function __construct()
    {
        parent::__construct('There is already an open swap request for this shift.', 409, 'SWAP_ALREADY_OPEN');
    }
}
