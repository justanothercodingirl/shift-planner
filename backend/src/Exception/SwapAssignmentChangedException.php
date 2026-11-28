<?php

declare(strict_types=1);

namespace App\Exception;

final class SwapAssignmentChangedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'The shift was changed by someone else in the meantime, so this request was cancelled.',
            409,
            'SWAP_ASSIGNMENT_CHANGED',
        );
    }
}
