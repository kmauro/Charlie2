<?php

namespace App\Domains\Professionals\Requests;

use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Models\ConsultingRoom;
use App\Domains\Professionals\Models\Professional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfessionalLeaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage', ConsultingRoom::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'professional_id' => ['required', 'integer', Rule::exists(Professional::class, 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists(Location::class, 'id')],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
