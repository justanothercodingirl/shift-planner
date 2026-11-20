<?php

declare(strict_types=1);

namespace App\Enum;

enum ContractType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case WorkingStudent = 'working_student';
    case MiniJob = 'mini_job';
}
