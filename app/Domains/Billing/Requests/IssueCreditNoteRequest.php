<?php

namespace App\Domains\Billing\Requests;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class IssueCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('billing-full');
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:500']];
    }
}
