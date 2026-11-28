<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A job for the queue: tell one employee about a change in a published roster.
 * The finished sentence travels with the job, because for a deleted shift the
 * old data is gone by the time the worker runs.
 */
final readonly class SendRosterChangedMail
{
    public function __construct(
        public int $rosterId,
        public int $employeeId,
        public string $description,
    ) {
    }
}
