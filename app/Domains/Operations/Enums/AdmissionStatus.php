<?php

namespace App\Domains\Operations\Enums;

enum AdmissionStatus: string
{
    case Active = 'active';
    case Discharged = 'discharged';
}
