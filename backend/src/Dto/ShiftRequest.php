<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\Position;
use Symfony\Component\Validator\Constraints as Assert;

/** Input for creating or updating a shift. Times are "HH:MM"; an end before the start means the next day. */
final readonly class ShiftRequest
{
    private const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';
    private const TIME_MESSAGE = 'This value should be a time like 08:30.';

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $date,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: self::TIME_PATTERN, message: self::TIME_MESSAGE)]
        public string $startTime,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: self::TIME_PATTERN, message: self::TIME_MESSAGE)]
        public string $endTime,
        public Position $requiredPosition,
        #[Assert\Positive]
        public int $headcount,
    ) {
    }
}
