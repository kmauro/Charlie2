<?php

namespace App\Domains\Appointments\Requests;

use App\Domains\Appointments\Enums\AppointmentType;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Models\ConsultingRoom;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $appointment = $this->route('appointment');

        return $user instanceof User
            && $appointment instanceof Appointment
            && $user->can('update', $appointment);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists((new Patient)->getTable(), 'id'),
            ],
            'professional_id' => [
                'required',
                'integer',
                Rule::exists((new Professional)->getTable(), 'id'),
            ],
            'location_id' => [
                'required',
                'integer',
                Rule::exists((new Location)->getTable(), 'id'),
            ],
            'consulting_room_id' => [
                'nullable',
                'integer',
                Rule::exists((new ConsultingRoom)->getTable(), 'id')->where('is_active', true),
            ],
            'starts_at' => ['required', 'date', 'after:now'],
            'type' => ['required', Rule::enum(AppointmentType::class)],
            'office' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:500'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'is_overbook' => ['prohibited'],
            'duration_minutes' => ['prohibited'],
            'ends_at' => ['prohibited'],
            'status' => ['prohibited'],
            'cancellation_reason' => ['prohibited'],
            'cancelled_at' => ['prohibited'],
            'cancelled_by' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'starts_at.after' => 'El turno debe programarse para una fecha y hora futuras.',
            'is_overbook.prohibited' => 'Los sobreturnos todavía no están habilitados.',
            'duration_minutes.prohibited' => 'La duración se obtiene de la configuración del profesional.',
            'ends_at.prohibited' => 'La finalización se calcula automáticamente.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'office' => $this->nullableSquishedString('office'),
            'reason' => $this->nullableSquishedString('reason'),
        ]);
    }

    private function nullableSquishedString(string $key): ?string
    {
        $value = $this->input($key);

        return filled($value) ? str($value)->squish()->toString() : null;
    }
}
