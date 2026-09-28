<?php

namespace App\Domains\Professionals\Requests;

use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Enums\ScheduleExceptionType;
use App\Domains\Professionals\Models\ConsultingRoom;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalScheduleException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProfessionalScheduleExceptionRequest extends FormRequest
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
            'location_id' => ['required', 'integer', Rule::exists(Location::class, 'id')],
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique(ProfessionalScheduleException::class, 'date')
                    ->where('professional_id', $this->integer('professional_id'))
                    ->where('location_id', $this->integer('location_id')),
            ],
            'type' => ['required', Rule::enum(ScheduleExceptionType::class)],
            'start_time' => [
                Rule::requiredIf($this->input('type') === ScheduleExceptionType::Available->value),
                'nullable',
                'date_format:H:i',
            ],
            'end_time' => [
                Rule::requiredIf($this->input('type') === ScheduleExceptionType::Available->value),
                'nullable',
                'date_format:H:i',
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('type') === ScheduleExceptionType::Available->value
                && $this->input('start_time') >= $this->input('end_time')) {
                $validator->errors()->add('end_time', 'La hora final debe ser posterior a la inicial.');
            }
        }];
    }
}
