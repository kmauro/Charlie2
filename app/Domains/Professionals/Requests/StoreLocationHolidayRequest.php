<?php

namespace App\Domains\Professionals\Requests;

use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Models\ConsultingRoom;
use App\Domains\Professionals\Models\LocationHoliday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationHolidayRequest extends FormRequest
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
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique(LocationHoliday::class, 'date')
                    ->where('location_id', $this->integer('location_id')),
            ],
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}
