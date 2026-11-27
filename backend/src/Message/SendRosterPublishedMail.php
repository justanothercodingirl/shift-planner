<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A job for the queue: tell one employee that a roster was published.
 * There is one job per employee, so a failing address is retried on its own
 * and never holds up the others. It carries ids only; the handler loads the rest.
 */
final readonly class SendRosterPublishedMail
{
    public function __construct(
        public int $rosterId,
        public int $employeeId,
    ) {
    }
}
