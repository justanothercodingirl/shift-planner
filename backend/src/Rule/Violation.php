<?php

declare(strict_types=1);

namespace App\Rule;

/** One broken rule: a stable code (for example OVERLAP) and a human reason. */
final readonly class Violation
{
    public function __construct(
        public string $code,
        public string $reason,
    ) {
    }

    /** @return array{code: string, reason: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'reason' => $this->reason];
    }
}
