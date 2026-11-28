<?php

declare(strict_types=1);

namespace App\Exception;

final class SwapRequestNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Swap request %d was not found.', $id), 404, 'SWAP_REQUEST_NOT_FOUND');
    }
}
