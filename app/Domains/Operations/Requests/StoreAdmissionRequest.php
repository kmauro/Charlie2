<?php

namespace App\Domains\Operations\Requests;

use App\Domains\Operations\Models\Admission;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdmissionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() instanceof User && $this->user()->can('create', Admission::class); }
    public function rules(): array { return ['patient_id' => ['required', 'integer', 'exists:patients,id'], 'location_id' => ['required', 'integer', 'exists:locations,id'], 'bed_id' => ['required', 'integer', 'exists:beds,id'], 'emergency_visit_id' => ['nullable', 'integer', 'exists:emergency_visits,id']]; }
}
