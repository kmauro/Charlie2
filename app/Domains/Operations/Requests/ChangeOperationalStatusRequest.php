<?php

namespace App\Domains\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeOperationalStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['lock_version' => ['required', 'integer', 'min:1'], 'outcome' => ['sometimes', 'string', 'in:discharge,admission,transfer'], 'cancellation_reason' => ['sometimes', 'string', 'max:120']]; }
}
