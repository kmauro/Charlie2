<?php

namespace App\Domains\Organizations\Requests;

use App\Domains\Organizations\Enums\LocationService;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $location = $this->location();

        return $location !== null
            && ($this->user()?->can('update', $location) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'legal_entity_id' => [
                'required',
                'integer',
                Rule::exists(LegalEntity::class, 'id')->where(
                    'status',
                    OrganizationStatus::Active->value,
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'regex:/^[A-Z0-9-]{2,20}$/',
                Rule::unique(Location::class, 'code')->ignore($this->location()),
            ],
            'address' => ['required', 'string', 'max:1000'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
            'opening_date' => ['nullable', 'date_format:Y-m-d'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'timezone', 'max:64'],
            'services' => ['required', 'array', 'min:1', 'max:7'],
            'services.*' => ['required', 'string', 'distinct', Rule::enum(LocationService::class)],
            'status' => [
                'required',
                Rule::in(array_column(
                    OrganizationStatus::locationEditableCases(),
                    'value',
                )),
            ],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $openingTime = $this->input('opening_time');
                $closingTime = $this->input('closing_time');

                if (is_string($openingTime) && is_string($closingTime)
                    && preg_match('/^\d{2}:\d{2}$/', $openingTime) === 1
                    && preg_match('/^\d{2}:\d{2}$/', $closingTime) === 1
                    && $closingTime <= $openingTime) {
                    $validator->errors()->add(
                        'closing_time',
                        'El horario de cierre debe ser posterior al horario de apertura.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'code' => Str::upper(Str::squish((string) $this->input('code'))),
            'address' => Str::squish((string) $this->input('address')),
            'neighborhood' => $this->nullableSquished('neighborhood'),
            'postal_code' => $this->nullableTrimmed('postal_code'),
            'phone' => $this->nullableTrimmed('phone'),
            'email' => filled($this->input('email'))
                ? Str::lower(trim((string) $this->input('email')))
                : null,
        ]);
    }

    private function location(): ?Location
    {
        $location = $this->route('location');

        return $location instanceof Location ? $location : null;
    }

    private function nullableSquished(string $key): ?string
    {
        return filled($this->input($key))
            ? Str::squish((string) $this->input($key))
            : null;
    }

    private function nullableTrimmed(string $key): ?string
    {
        return filled($this->input($key))
            ? trim((string) $this->input($key))
            : null;
    }
}
