<?php

namespace App\Domains\Appointments\Requests;

use App\Domains\Appointments\Enums\AppointmentType;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaitlistEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Appointment::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', Rule::exists((new Patient)->getTable(), 'id')],
            'professional_id' => ['nullable', 'integer', Rule::exists((new Professional)->getTable(), 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists((new Location)->getTable(), 'id')],
            'type' => ['required', Rule::enum(AppointmentType::class)],
            'desired_from' => ['nullable', 'date'],
            'priority' => ['required', 'integer', 'between:0,9'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');

        $this->merge([
            'notes' => filled($notes) ? str((string) $notes)->squish()->toString() : null,
        ]);
    }
}
