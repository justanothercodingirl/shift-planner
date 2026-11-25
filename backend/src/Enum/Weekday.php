<?php

declare(strict_types=1);

namespace App\Enum;

enum Weekday: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    /** The weekday a date falls on. */
    public static function fromDate(\DateTimeImmutable $date): self
    {
        return self::cases()[(int) $date->format('N') - 1];
    }

    /** The name for messages: "Monday". */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** 1 for Monday to 7 for Sunday, used to sort a week in order. */
    public function isoNumber(): int
    {
        return (int) array_search($this, self::cases(), true) + 1;
    }
}
