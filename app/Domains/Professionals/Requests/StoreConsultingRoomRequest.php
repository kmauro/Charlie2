<?php

namespace App\Domains\Professionals\Requests;

use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Models\ConsultingRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreConsultingRoomRequest extends FormRequest
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
            'location_id' => ['required', 'integer', Rule::exists(Location::class, 'id')],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(ConsultingRoom::class, 'code')
                    ->where('location_id', $this->integer('location_id')),
            ],
            'sector' => ['nullable', 'string', 'max:120'],
            'floor' => ['nullable', 'string', 'max:50'],
            'equipment' => ['nullable', 'array', 'max:20'],
            'equipment.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'operational_restrictions' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'code' => Str::upper(Str::squish((string) $this->input('code'))),
        ]);
    }
}
