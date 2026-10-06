<?php

namespace App\Domains\Billing\Requests;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CloseCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('billing-cash');
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['counted_amount' => ['required', 'numeric', 'gte:0']];
    }
}
