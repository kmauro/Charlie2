<?php

namespace App\Domains\Appointments\Requests;

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Users\Models\User;
use Illuminate\Validation\Rule;

class StoreRecurringAppointmentRequest extends StoreAppointmentRequest
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
            ...parent::rules(),
            'recurrence_frequency' => ['required', Rule::in(['weekly', 'biweekly', 'monthly'])],
            'recurrence_ends_on' => ['nullable', 'date', 'after_or_equal:starts_at', 'required_without:recurrence_occurrences'],
            'recurrence_occurrences' => ['nullable', 'integer', 'between:2,52', 'required_without:recurrence_ends_on'],
        ];
    }
}
