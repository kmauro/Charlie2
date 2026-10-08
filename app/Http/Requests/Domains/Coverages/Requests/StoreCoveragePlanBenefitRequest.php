<?php

namespace App\Http\Requests\Domains\Coverages\Requests;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreCoveragePlanBenefitRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'coverage_percentage' => ['required', 'numeric', 'between:0,100'],
            'copay_amount' => ['nullable', 'numeric', 'gte:0'],
            'annual_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
