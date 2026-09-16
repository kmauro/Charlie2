<?php

namespace App\Domains\Professionals\Services;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Enums\OfficeAssignmentType;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Repositories\Contracts\ProfessionalRepository;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfessionalService
{
    public function __construct(
        private ProfessionalRepository $professionals,
        private UserService $users,
    ) {}

    /**
     * @param  array{search?: string, status?: string, specialty?: string}  $filters
     * @return LengthAwarePaginator<int, Professional>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $normalizedFilters = Arr::only($filters, ['search', 'status', 'specialty']);

        if (isset($normalizedFilters['search'])) {
            $normalizedFilters['search'] = Str::squish($normalizedFilters['search']);
        }

        return $this->professionals->paginate(
            array_filter($normalizedFilters, fn (mixed $value): bool => $value !== null && $value !== ''),
            min(max($perPage, 1), 100),
        );
    }

    public function find(int $id): Professional
    {
        return $this->professionals->findOrFail($id);
    }

    /**
     * @return array{total: int, active: int, inactive: int, on_leave: int, invitation_pending: int}
     */
    public function summaries(): array
    {
        return $this->professionals->summaries();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{professional: Professional, invitation_url: string}
     */
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $invitation = $this->users->inviteProfessional([
                'name' => Str::squish($data['name']),
                'email' => Str::lower(trim($data['email'])),
                'permissions' => [],
                'expires_in_hours' => (int) ($data['expires_in_hours'] ?? 72),
            ]);

            $professional = $this->professionals->create(
                [
                    'user_id' => $invitation->user->getKey(),
                    ...$this->professionalAttributes($data),
                    'status' => ProfessionalStatus::Active,
                ],
                $this->scheduleAttributes($data['schedules']),
            );

            return [
                'professional' => $professional,
                'invitation_url' => $invitation->invitationUrl,
            ];
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{professional: Professional, invitation_url: string|null}
     */
    public function update(Professional $professional, array $data): array
    {
        return DB::transaction(function () use ($professional, $data): array {
            $professional->loadMissing('user');
            $previousStatus = $professional->status;
            $nextStatus = ProfessionalStatus::from($data['status']);
            $invitationUrl = null;

            $this->users->update($professional->user, [
                'name' => Str::squish($data['name']),
                'email' => Str::lower(trim($data['email'])),
            ]);

            if ($nextStatus === ProfessionalStatus::Inactive) {
                $this->users->suspendProfessional($professional->user);
            } elseif ($previousStatus === ProfessionalStatus::Inactive) {
                $invitationUrl = $this->restoreProfessionalAccount(
                    $professional->user,
                );
            }

            return [
                'professional' => $this->professionals->update(
                    $professional,
                    $this->professionalAttributes($data),
                    $this->scheduleAttributes($data['schedules']),
                ),
                'invitation_url' => $invitationUrl,
            ];
        }, 3);
    }

    public function deactivate(Professional $professional): Professional
    {
        return DB::transaction(function () use ($professional): Professional {
            $professional->loadMissing('user');
            $this->users->suspendProfessional($professional->user);

            return $this->professionals->setStatus($professional, ProfessionalStatus::Inactive);
        }, 3);
    }

    /**
     * @return array{professional: Professional, invitation_url: string|null}
     */
    public function restore(Professional $professional): array
    {
        return DB::transaction(function () use ($professional): array {
            $professional->loadMissing('user');
            $invitationUrl = $this->restoreProfessionalAccount(
                $professional->user,
            );

            return [
                'professional' => $this->professionals->setStatus(
                    $professional,
                    ProfessionalStatus::Active,
                ),
                'invitation_url' => $invitationUrl,
            ];
        }, 3);
    }

    private function restoreProfessionalAccount(User $user): ?string
    {
        $user = $this->users->restoreProfessional($user);

        if ($user->status !== UserStatus::PendingInvitation) {
            return null;
        }

        return $this->users->reinvite($user)->invitationUrl;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function professionalAttributes(array $data): array
    {
        $officeAssignmentType = OfficeAssignmentType::from($data['office_assignment_type']);

        $attributes = [
            'document_number' => trim($data['document_number']),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'specialty' => Str::squish($data['specialty']),
            'registration_type' => trim($data['registration_type']),
            'registration_number' => trim($data['registration_number']),
            'appointment_duration_minutes' => (int) $data['appointment_duration_minutes'],
            'office_assignment_type' => $officeAssignmentType,
            'fixed_office' => $officeAssignmentType === OfficeAssignmentType::Fixed
                ? Str::squish($data['fixed_office'])
                : null,
        ];

        if (array_key_exists('status', $data)) {
            $attributes['status'] = ProfessionalStatus::from($data['status']);
        }

        return $attributes;
    }

    /**
     * @param  array<int, array{location_id: int|string, weekday: int|string, start_time: string, end_time: string}>  $schedules
     * @return array<int, array{location_id: int, location_name: string, weekday: int, start_time: string, end_time: string}>
     */
    private function scheduleAttributes(array $schedules): array
    {
        $locationIds = collect($schedules)
            ->pluck('location_id')
            ->map(fn (mixed $locationId): int => (int) $locationId)
            ->unique()
            ->values();
        $locations = Location::query()
            ->select(['id', 'name', 'status', 'archived_at'])
            ->whereKey($locationIds)
            ->where('status', OrganizationStatus::Active->value)
            ->whereNull('archived_at')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($locations->count() !== $locationIds->count()) {
            throw ValidationException::withMessages([
                'schedules' => 'Todas las sedes de atención deben estar activas.',
            ]);
        }

        return array_map(
            function (array $schedule) use ($locations): array {
                $locationId = (int) $schedule['location_id'];
                $location = $locations->get($locationId);

                if (! $location instanceof Location) {
                    throw ValidationException::withMessages([
                        'schedules' => 'La sede de atención seleccionada no está disponible.',
                    ]);
                }

                return [
                    'location_id' => $locationId,
                    'location_name' => $location->name,
                    'weekday' => (int) $schedule['weekday'],
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time'],
                ];
            },
            $schedules,
        );
    }
}
