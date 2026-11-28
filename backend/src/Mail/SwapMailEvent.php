<?php

declare(strict_types=1);

namespace App\Mail;

/** The steps of a swap that send a mail. */
enum SwapMailEvent: string
{
    /** An employee asked a colleague to take over a shift (mail to the colleague). */
    case Requested = 'requested';
    /** The colleague agreed and the manager has to decide (mail to the managers). */
    case Accepted = 'accepted';
    /** The colleague said no (mail to the requester). */
    case Declined = 'declined';
    /** The requester withdrew an open request (mail to the colleague, and the managers if it was accepted). */
    case Cancelled = 'cancelled';
    /** The shift was changed or removed meanwhile, so the request was cancelled (mail to both people). */
    case ShiftChanged = 'shift_changed';
    /** The manager approved and the shift was handed over (mail to both people). */
    case Approved = 'approved';
    /** The manager said no (mail to both people). */
    case Rejected = 'rejected';
}
