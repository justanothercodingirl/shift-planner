<?php

declare(strict_types=1);

namespace App\Exception;

final class SwapWithYourselfException extends DomainException
{
    public function __construct()
    {
        parent::__construct('You cannot swap a shift with yourself.', 422, 'SWAP_WITH_YOURSELF');
    }
}
