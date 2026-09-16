<?php

namespace App\Domains\ExternalClinicalRecords\Services;

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\User;

class ExternalClinicalAccessService
{
    public function canAccessPatient(User $user, Patient $patient): bool
    {
        if (
            $user->status !== UserStatus::Active
            || ! $user->hasPermission(UserPermission::AccessExternalClinicalRecord)
            || ! in_array($patient->status, [
                PatientStatus::Active,
                PatientStatus::Inpatient,
            ], true)
        ) {
            return false;
        }

        $appointments = Appointment::query()
            ->whereBelongsTo($patient)
            ->whereIn('status', [
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Waiting->value,
                AppointmentStatus::Present->value,
                AppointmentStatus::Attended->value,
            ])
            ->where('starts_at', '<=', now()->addHours(24))
            ->where('ends_at', '>=', now()->subHours(24));

        if ($user->hasRole(RoleCode::Professional)) {
            return $appointments
                ->whereHas('professional', fn ($query) => $query
                    ->where('user_id', $user->getKey())
                    ->where('status', ProfessionalStatus::Active->value))
                ->exists();
        }

        if ($user->hasRole(RoleCode::Supervisor)) {
            return $appointments
                ->whereIn('location_id', $user->locations()->select('locations.id'))
                ->exists();
        }

        return false;
    }
}
