<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Roster;

/** What publishing returns: the published roster and the warnings (publishing still happened). */
final readonly class PublishResult
{
    /**
     * @param list<array{code: string, reason: string}> $warnings
     */
    public function __construct(
        public Roster $roster,
        public array $warnings,
    ) {
    }
}
