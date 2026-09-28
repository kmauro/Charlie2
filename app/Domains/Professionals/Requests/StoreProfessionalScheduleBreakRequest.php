<?php

namespace App\Domains\Professionals\Requests;

use App\Domains\Professionals\Models\ConsultingRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProfessionalScheduleBreakRequest extends FormRequest
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
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'label' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('start_time') >= $this->input('end_time')) {
                $validator->errors()->add('end_time', 'La hora final debe ser posterior a la inicial.');
            }
        }];
    }
}
