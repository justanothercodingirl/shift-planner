<?php

declare(strict_types=1);

namespace App\Enum;

enum Position: string
{
    case Barista = 'barista';
    case Service = 'service';
    case Kitchen = 'kitchen';
    case Bar = 'bar';
    case ShiftLead = 'shift_lead';

    /** The position in plain words for messages: "shift_lead" becomes "shift lead". */
    public function label(): string
    {
        return str_replace('_', ' ', $this->value);
    }
}
