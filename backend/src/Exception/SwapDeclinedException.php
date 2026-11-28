<?php

declare(strict_types=1);

namespace App\Exception;

final class SwapDeclinedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The colleague declined this request, so it cannot be approved.', 409, 'SWAP_DECLINED');
    }
}
