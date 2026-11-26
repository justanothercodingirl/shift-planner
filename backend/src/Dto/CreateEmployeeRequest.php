<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ContractType;
use App\Enum\Position;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateEmployeeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 72)]
        public string $password,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        public Position $position,
        public ContractType $contractType,
        #[Assert\Positive]
        public int $maxWeeklyHours,
        /** @var list<Position> extra positions besides the main one; optional */
        public array $additionalPositions = [],
    ) {
    }
}
