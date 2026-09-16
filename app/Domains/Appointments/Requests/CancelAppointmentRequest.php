<?php

namespace App\Domains\Appointments\Requests;

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $appointment = $this->route('appointment');

        return $user instanceof User
            && $appointment instanceof Appointment
            && $user->can('cancel', $appointment);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'max:500'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('cancellation_reason');

        $this->merge([
            'cancellation_reason' => filled($reason)
                ? str($reason)->squish()->toString()
                : null,
        ]);
    }
}
