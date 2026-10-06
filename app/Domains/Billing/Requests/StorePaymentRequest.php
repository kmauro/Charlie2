<?php

namespace App\Domains\Billing\Requests;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('billing-payment');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', Rule::exists((new Invoice)->getTable(), 'id')],
            'payment_method_id' => ['required', 'integer', Rule::exists((new PaymentMethod)->getTable(), 'id')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
