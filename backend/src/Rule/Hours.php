<?php

declare(strict_types=1);

namespace App\Rule;

/** Writes numbers of hours for rule messages. */
final class Hours
{
    /** 9.0 becomes "9", 9.5 becomes "9.5": whole hours without decimals, anything else with one. */
    public static function format(float $hours): string
    {
        return rtrim(rtrim(number_format(round($hours, 1), 1, '.', ''), '0'), '.');
    }
}
