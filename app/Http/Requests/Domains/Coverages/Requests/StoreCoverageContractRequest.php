<?php

namespace App\Http\Requests\Domains\Coverages\Requests;

use App\Domains\Coverages\Models\CoveragePlan;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoverageContractRequest extends FormRequest
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
            'location_id' => ['required', 'integer', Rule::exists((new Location)->getTable(), 'id')],
            'patient_id' => ['required', 'integer', Rule::exists((new Patient)->getTable(), 'id')],
            'coverage_plan_id' => ['required', 'integer', Rule::exists((new CoveragePlan)->getTable(), 'id')],
            'member_number' => ['nullable', 'string', 'max:80'],
            'payer_type' => ['required', Rule::in(['patient', 'coverage', 'mixed'])],
            'coverage_amount' => ['nullable', 'numeric', 'gte:0'],
            'recurring_amount' => ['required', 'numeric', 'gt:0'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
