<?php

namespace App\Domains\Professionals\Requests;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Enums\OfficeAssignmentType;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfessionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $professional = $this->professional();

        return $professional !== null
            && ($this->user()?->can('update', $professional) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $professional = $this->professional();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($professional?->user_id),
            ],
            'document_number' => [
                'required',
                'string',
                'regex:/^\d{7,9}$/',
                Rule::unique(Professional::class, 'document_number')->ignore($professional),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'specialty' => ['required', 'string', 'max:120'],
            'registration_type' => ['required', 'string', Rule::in(['M.N.', 'M.P.'])],
            'registration_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Professional::class, 'registration_number')
                    ->ignore($professional)
                    ->where(fn ($query) => $query->where('registration_type', $this->input('registration_type'))),
            ],
            'appointment_duration_minutes' => ['required', 'integer', 'between:10,180'],
            'status' => ['required', Rule::enum(ProfessionalStatus::class)],
            'office_assignment_type' => ['required', Rule::enum(OfficeAssignmentType::class)],
            'fixed_office' => [
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf(fn (): bool => $this->input('office_assignment_type') === OfficeAssignmentType::Fixed->value),
            ],
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*' => ['required', 'array:location_id,weekday,start_time,end_time'],
            'schedules.*.location_id' => [
                'required',
                'integer',
                Rule::exists(Location::class, 'id')->where(
                    fn ($query) => $query
                        ->where('status', OrganizationStatus::Active->value)
                        ->whereNull('archived_at'),
                ),
            ],
            'schedules.*.weekday' => ['required', 'integer', 'between:1,7'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateScheduleRanges($validator);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of((string) $this->input('name'))->squish()->toString(),
            'email' => Str::of((string) $this->input('email'))->trim()->lower()->toString(),
        ]);
    }

    private function professional(): ?Professional
    {
        $professional = $this->route('professional');

        return $professional instanceof Professional ? $professional : null;
    }

    private function validateScheduleRanges(Validator $validator): void
    {
        /** @var array<int, array<int, array{index: int, start: string, end: string}>> $rangesByDay */
        $rangesByDay = [];
        $schedules = $this->input('schedules', []);

        if (! is_array($schedules)) {
            return;
        }

        foreach ($schedules as $index => $schedule) {
            if (! is_array($schedule)) {
                continue;
            }

            $startTime = $schedule['start_time'] ?? null;
            $endTime = $schedule['end_time'] ?? null;
            $weekday = $schedule['weekday'] ?? null;

            if (! is_string($startTime) || ! is_string($endTime)
                || preg_match('/^\d{2}:\d{2}$/', $startTime) !== 1
                || preg_match('/^\d{2}:\d{2}$/', $endTime) !== 1) {
                continue;
            }

            if ($startTime >= $endTime) {
                $validator->errors()->add(
                    "schedules.$index.end_time",
                    'El horario de finalización debe ser posterior al horario de inicio.',
                );

                continue;
            }

            if (! is_numeric($weekday)) {
                continue;
            }

            $rangesByDay[(int) $weekday][] = [
                'index' => (int) $index,
                'start' => $startTime,
                'end' => $endTime,
            ];
        }

        foreach ($rangesByDay as $ranges) {
            usort($ranges, fn (array $left, array $right): int => $left['start'] <=> $right['start']);

            $previousEnd = null;

            foreach ($ranges as $range) {
                if ($previousEnd !== null && $range['start'] < $previousEnd) {
                    $validator->errors()->add(
                        "schedules.{$range['index']}.start_time",
                        'El horario se superpone con otra franja del mismo día.',
                    );
                }

                if ($previousEnd === null || $range['end'] > $previousEnd) {
                    $previousEnd = $range['end'];
                }
            }
        }
    }
}
