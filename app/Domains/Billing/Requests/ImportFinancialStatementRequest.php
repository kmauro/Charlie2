<?php

namespace App\Domains\Billing\Requests;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ImportFinancialStatementRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() instanceof User && $this->user()->can('billing-accounting'); }
    public function rules(): array { return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]; }
}
