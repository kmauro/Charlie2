<?php

namespace App\Domains\Patients\Requests;

use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdatePatientPortalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->hasRole(RoleCode::Patient)
            && $user->patient?->canAccessPortal() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $patient = $this->user()?->patient;

        return [
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique((new Patient)->getTable(), 'email')->ignore($patient),
                Rule::unique((new User)->getTable(), 'email')->ignore($this->user()?->getKey()),
            ],
            'address' => ['nullable', 'string', 'max:1000'],
            'appointment_reminders_by_email' => ['sometimes', 'boolean'],
            'appointment_reminders_by_whatsapp' => ['sometimes', 'boolean'],
            'appointment_reminder_hours' => ['sometimes', 'integer', 'in:12,24,48'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => filled($this->input('email'))
                ? Str::lower(trim((string) $this->input('email')))
                : null,
            'address' => filled($this->input('address'))
                ? Str::squish((string) $this->input('address'))
                : null,
        ]);
    }
}
