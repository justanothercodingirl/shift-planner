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
}
