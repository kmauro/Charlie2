<?php

namespace App\Domains\Billing\Requests;

use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('billing-invoice');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', Rule::exists((new Location)->getTable(), 'id')],
            'patient_id' => ['nullable', 'integer', Rule::exists((new Patient)->getTable(), 'id')],
            'payer_type' => ['required', Rule::in(['patient', 'coverage', 'mixed'])],
            'coverage_name' => ['nullable', 'string', 'max:120', 'required_if:payer_type,coverage,mixed'],
            'coverage_amount' => ['nullable', 'numeric', 'gte:0'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'type' => ['required', Rule::in(['invoice', 'advance'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'installments' => ['nullable', 'array', 'min:1'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0'],
            'installments.*.due_date' => ['required', 'date'],
        ];
    }
}
