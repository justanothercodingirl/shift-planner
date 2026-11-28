<?php

declare(strict_types=1);

namespace App\Message;

use App\Mail\SwapMailEvent;

/**
 * A job for the queue: tell one person about one step of a swap. One job per
 * recipient, so a failing address is retried on its own. The recipient is a
 * user, because managers do not need an employee profile.
 */
final readonly class SendSwapMail
{
    public function __construct(
        public int $swapRequestId,
        public int $recipientUserId,
        public SwapMailEvent $event,
    ) {
    }
}
