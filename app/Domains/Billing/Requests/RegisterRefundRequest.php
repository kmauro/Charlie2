<?php

namespace App\Domains\Billing\Requests;

use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('billing-full');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'payment_method_id' => ['required', 'integer', Rule::exists((new PaymentMethod)->getTable(), 'id')],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
