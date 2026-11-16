<?php

namespace App\Domains\Operations\Requests;

use App\Domains\Operations\Models\EmergencyVisit;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmergencyVisitRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() instanceof User && $this->user()->can('create', EmergencyVisit::class); }
    public function rules(): array { return ['patient_id' => ['required', 'integer', 'exists:patients,id'], 'location_id' => ['required', 'integer', 'exists:locations,id'], 'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'], 'priority' => ['nullable', 'integer', 'between:1,5'], 'arrived_at' => ['nullable', 'date']]; }
}
