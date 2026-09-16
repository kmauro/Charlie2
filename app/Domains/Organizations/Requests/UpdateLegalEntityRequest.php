<?php

namespace App\Domains\Organizations\Requests;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\LegalEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateLegalEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $legalEntity = $this->legalEntity();

        return $legalEntity !== null
            && ($this->user()?->can('update', $legalEntity) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $legalEntity = $this->legalEntity();

        return [
            'business_name' => ['required', 'string', 'max:255'],
            'cuit' => [
                'required',
                'string',
                'regex:/^\d{11}$/',
                Rule::unique(LegalEntity::class, 'cuit')->ignore($legalEntity),
            ],
            'fiscal_address' => ['required', 'string', 'max:1000'],
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
        ]);
    }

    private function legalEntity(): ?LegalEntity
    {
        $legalEntity = $this->route('legalEntity')
            ?? $this->route('legal_entity');

        return $legalEntity instanceof LegalEntity ? $legalEntity : null;
    }
}
