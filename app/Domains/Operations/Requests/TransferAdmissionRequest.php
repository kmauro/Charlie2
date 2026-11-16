<?php

namespace App\Domains\Operations\Requests;

use App\Domains\Operations\Models\Admission;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class TransferAdmissionRequest extends FormRequest
{
    public function authorize(): bool { $admission = $this->route('admission'); return $this->user() instanceof User && $admission instanceof Admission && $this->user()->can('update', $admission); }
    public function rules(): array { return ['bed_id' => ['required', 'integer', 'exists:beds,id'], 'lock_version' => ['required', 'integer', 'min:1']]; }
}
