<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case Manager = 'manager';
    case Employee = 'employee';

    public function toSecurityRole(): string
    {
        return 'ROLE_'.strtoupper($this->value);
    }
}
