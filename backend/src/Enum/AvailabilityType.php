<?php

declare(strict_types=1);

namespace App\Enum;

enum AvailabilityType: string
{
    /** Blocks an assignment. */
    case Unavailable = 'unavailable';
    /** Only a hint for the manager; never blocks. */
    case Preferred = 'preferred';
}
