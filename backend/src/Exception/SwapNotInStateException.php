<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\SwapStatus;

final class SwapNotInStateException extends DomainException
{
    public function __construct(SwapStatus $current, SwapStatus $wanted)
    {
        parent::__construct(
            sprintf('A swap request that is %s cannot become %s.', $current->value, $wanted->value),
            409,
            'SWAP_NOT_IN_STATE',
        );
    }
}
