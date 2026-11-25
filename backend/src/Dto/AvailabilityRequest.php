<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\AvailabilityType;
use App\Enum\Weekday;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Input for creating or updating an availability entry. Exactly one of weekday
 * (repeats every week) and date (once, "YYYY-MM-DD") is set. Times are "HH:MM".
 */
final readonly class AvailabilityRequest
{
    private const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';
    private const TIME_MESSAGE = 'This value should be a time like 08:30.';

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: self::TIME_PATTERN, message: self::TIME_MESSAGE)]
        public string $startTime,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: self::TIME_PATTERN, message: self::TIME_MESSAGE)]
        public string $endTime,
        public AvailabilityType $type,
        public ?Weekday $weekday = null,
        #[Assert\Date]
        public ?string $date = null,
    ) {
    }

    #[Assert\Callback]
    public function validateWeekdayOrDate(ExecutionContextInterface $context): void
    {
        if ((null === $this->weekday) === (null === $this->date)) {
            $context->buildViolation('Set either a weekday or a date, not both and not neither.')
                ->atPath('weekday')
                ->addViolation();
        }
    }
}
