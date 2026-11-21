<?php

declare(strict_types=1);

namespace App\Exception;

final class ShiftNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Shift %d was not found.', $id), 404, 'SHIFT_NOT_FOUND');
    }
}
