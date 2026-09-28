<?php

namespace App\Domains\Organizations\Requests;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\LegalEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreLegalEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LegalEntity::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'cuit' => [
                'required',
                'string',
                'regex:/^\d{11}$/',
                Rule::unique(LegalEntity::class, 'cuit'),
            ],
            'tax_condition' => ['required', Rule::in(['responsable_inscripto', 'monotributo', 'exento', 'consumidor_final'])],
            'fiscal_address' => ['required', 'string', 'max:1000'],
            'fiscal_point_of_sale' => ['nullable', 'integer', 'between:1,99999'],
            'arca_environment' => ['nullable', Rule::in(['homologacion', 'produccion'])],
            'arca_certificate' => ['nullable', 'string', 'max:30000'],
            'arca_private_key' => ['nullable', 'string', 'max:30000'],
            'status' => [
                'required',
                Rule::in(array_column(
                    OrganizationStatus::legalEntityEditableCases(),
                    'value',
                )),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'business_name' => Str::squish((string) $this->input('business_name')),
            'cuit' => (string) preg_replace('/\D+/', '', (string) $this->input('cuit')),
            'fiscal_address' => Str::squish((string) $this->input('fiscal_address')),
            'tax_condition' => $this->input('tax_condition', 'responsable_inscripto'),
            'fiscal_point_of_sale' => $this->input('fiscal_point_of_sale'),
            'arca_environment' => $this->input('arca_environment'),
            'status' => $this->input('status', OrganizationStatus::Active->value),
        ]);
    }
}
