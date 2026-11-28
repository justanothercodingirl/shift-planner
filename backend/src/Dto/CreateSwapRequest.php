<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Input for asking a colleague to take over a shift. */
final readonly class CreateSwapRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $targetEmployeeId,
    ) {
    }
}
