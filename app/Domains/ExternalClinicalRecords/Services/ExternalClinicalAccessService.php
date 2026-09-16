<?php

namespace App\Domains\ExternalClinicalRecords\Services;

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;

class ExternalClinicalAccessService
{
    public function canAccessPatient(User $user, Patient $patient): bool
    {
        if (! $user->hasRole(RoleCode::Professional)) {
            return false;
        }

        return Appointment::query()
            ->whereBelongsTo($patient)
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::Absent->value,
            ])
            ->whereHas('professional', fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->where('status', ProfessionalStatus::Active->value))
            ->exists();
    }
}
