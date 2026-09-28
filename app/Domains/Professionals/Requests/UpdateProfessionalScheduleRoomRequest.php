<?php

namespace App\Domains\Professionals\Requests;

use App\Domains\Professionals\Models\ConsultingRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfessionalScheduleRoomRequest extends FormRequest
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
            'consulting_room_id' => [
                'nullable',
                'integer',
                Rule::exists(ConsultingRoom::class, 'id')->where('is_active', true),
            ],
        ];
    }
}
