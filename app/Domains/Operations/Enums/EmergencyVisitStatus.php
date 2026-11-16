<?php

namespace App\Domains\Operations\Enums;

enum EmergencyVisitStatus: string
{
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
