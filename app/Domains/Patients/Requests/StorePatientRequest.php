<?php

namespace App\Domains\Patients\Requests;

use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Enums\RegisteredSex;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Patient::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_number' => [
                'required',
                'string',
                'regex:/^\d{7,9}$/',
                Rule::unique(Patient::class, 'document_number'),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'registered_sex' => ['required', Rule::enum(RegisteredSex::class)],
            'gender' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(Patient::class, 'email'),
                Rule::unique(User::class, 'email'),
            ],
            'address' => ['nullable', 'string', 'max:1000'],
            'coverage_name' => ['required', 'string', 'max:120'],
            'member_number' => [
                'nullable',
                'string',
                'max:80',
                Rule::requiredIf(fn (): bool => ! $this->hasPrivateCoverage()),
            ],
            'status' => ['required', Rule::in($this->allowedStatusValues())],
            'contacts' => ['nullable', 'array', 'max:20'],
            'contacts.*' => [
                'required',
                'array:full_name,relationship,phone,email,is_emergency_contact,is_legal_representative,is_financially_responsible',
            ],
            'contacts.*.full_name' => ['required', 'string', 'max:255'],
            'contacts.*.relationship' => ['required', 'string', 'max:120'],
            'contacts.*.phone' => ['nullable', 'string', 'max:32'],
            'contacts.*.email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
            'contacts.*.is_emergency_contact' => ['sometimes', 'boolean'],
            'contacts.*.is_legal_representative' => ['sometimes', 'boolean'],
            'contacts.*.is_financially_responsible' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateMinorHasLegalRepresentative($validator);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizedInput = [
            'document_number' => (string) preg_replace(
                '/\D+/',
                '',
                (string) $this->input('document_number'),
            ),
            'first_name' => Str::squish((string) $this->input('first_name')),
            'last_name' => Str::squish((string) $this->input('last_name')),
            'email' => filled($this->input('email'))
                ? Str::lower(trim((string) $this->input('email')))
                : null,
            'coverage_name' => Str::squish((string) $this->input('coverage_name')),
            'status' => $this->input('status', PatientStatus::Active->value),
        ];

        $contacts = $this->input('contacts');

        if (is_array($contacts)) {
            $normalizedInput['contacts'] = $this->normalizedContacts($contacts);
        }

        $this->merge($normalizedInput);
    }

    /**
     * @param  array<int, mixed>  $contacts
     * @return array<int, mixed>
     */
    private function normalizedContacts(array $contacts): array
    {
        return array_map(function (mixed $contact): mixed {
            if (! is_array($contact) || ! array_key_exists('email', $contact)) {
                return $contact;
            }

            $email = $contact['email'];

            if (is_string($email)) {
                $contact['email'] = filled($email)
                    ? Str::lower(trim($email))
                    : null;
            }

            return $contact;
        }, $contacts);
    }

    /**
     * @return array<int, string>
     */
    private function allowedStatusValues(): array
    {
        if (! ($this->user()?->can('manageStatus', Patient::class) ?? false)) {
            return [PatientStatus::Active->value];
        }

        return array_map(
            fn (PatientStatus $status): string => $status->value,
            PatientStatus::editableCases(),
        );
    }

    private function hasPrivateCoverage(): bool
    {
        return Str::lower(Str::squish((string) $this->input('coverage_name'))) === 'particular';
    }

    private function validateMinorHasLegalRepresentative(Validator $validator): void
    {
        $birthDateValue = $this->input('birth_date');

        if (! is_string($birthDateValue)) {
            return;
        }

        try {
            $birthDate = CarbonImmutable::createFromFormat('!Y-m-d', $birthDateValue);
        } catch (Throwable) {
            return;
        }

        if ($birthDate->format('Y-m-d') !== $birthDateValue
            || $birthDate->lessThanOrEqualTo(now()->subYears(18)->startOfDay())) {
            return;
        }

        $contacts = $this->input('contacts', []);

        if (! is_array($contacts)) {
            return;
        }

        foreach ($contacts as $contact) {
            if (is_array($contact)
                && filter_var(
                    $contact['is_legal_representative'] ?? false,
                    FILTER_VALIDATE_BOOL,
                )) {
                return;
            }
        }

        $validator->errors()->add(
            'contacts',
            'Los pacientes menores de edad requieren al menos un representante legal.',
        );
    }
}
