<?php

declare(strict_types=1);

namespace App\Mail;

/** Writes a roster week the same way in every mail and PDF. */
final class WeekLabel
{
    /** "3 Feb – 9 Feb 2031" for the week that starts on the given Monday. */
    public static function of(\DateTimeImmutable $weekStart): string
    {
        return $weekStart->format('j M').' – '.$weekStart->modify('+6 days')->format('j M Y');
    }
}
