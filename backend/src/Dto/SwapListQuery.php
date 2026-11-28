<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\SwapStatus;

/** The optional filter of the swap list: /api/swap-requests?status=accepted. An unknown status is a 422. */
final readonly class SwapListQuery
{
    public function __construct(
        public ?SwapStatus $status = null,
    ) {
    }
}
