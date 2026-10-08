<?php

namespace App\Http\Requests\Domains\Coverages\Requests;

use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoveragePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('billing-full');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'legal_entity_id' => ['required', 'integer', Rule::exists((new LegalEntity)->getTable(), 'id')],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'recurring_amount' => ['required', 'numeric', 'gte:0'],
        ];
    }
}
