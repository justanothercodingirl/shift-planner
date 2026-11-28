<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Shift;

/** Writes a shift in words for mails, the same way everywhere. */
final class ShiftDescription
{
    /** "Mon 3 Feb 08:00 to 14:00 (barista)", with "+1 day" after the end of an overnight shift. */
    public static function of(Shift $shift): string
    {
        $overnight = $shift->getEndTime()->format('H:i') < $shift->getStartTime()->format('H:i');

        return sprintf(
            '%s %s to %s%s (%s)',
            $shift->getDate()->format('D j M'),
            $shift->getStartTime()->format('H:i'),
            $shift->getEndTime()->format('H:i'),
            $overnight ? ' +1 day' : '',
            $shift->getRequiredPosition()->label(),
        );
    }
}
