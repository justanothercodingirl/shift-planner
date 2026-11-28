<?php

declare(strict_types=1);

namespace App\Enum;

enum SwapStatus: string
{
    /** Asked, the colleague has not answered yet. */
    case Pending = 'pending';
    /** The colleague agreed; the manager still has to approve. */
    case Accepted = 'accepted';
    /** The colleague said no. */
    case Declined = 'declined';
    /** The manager approved and the shift was handed over. */
    case Approved = 'approved';
    /** The manager said no. */
    case Rejected = 'rejected';
    /** The requester withdrew, or the shift changed under the request. */
    case Cancelled = 'cancelled';

    /** Still waiting for someone: pending or accepted. */
    public function isOpen(): bool
    {
        return self::Pending === $this || self::Accepted === $this;
    }

    /** The allowed steps; declined, approved, rejected and cancelled are final. */
    public function canMoveTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Accepted, self::Declined, self::Rejected, self::Cancelled], true),
            self::Accepted => in_array($next, [self::Approved, self::Rejected, self::Cancelled], true),
            default => false,
        };
    }
}
