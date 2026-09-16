<?php

namespace App\Domains\Professionals\Repositories\Eloquent;

use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Repositories\Contracts\ProfessionalRepository;
use App\Domains\Users\Enums\UserStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentProfessionalRepository implements ProfessionalRepository
{
    /**
     * @param  array{search?: string, status?: string, specialty?: string}  $filters
     * @return LengthAwarePaginator<int, Professional>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return Professional::query()
            ->select([
                'id',
                'user_id',
                'document_number',
                'phone',
                'specialty',
                'registration_type',
                'registration_number',
                'appointment_duration_minutes',
                'status',
                'office_assignment_type',
                'fixed_office',
                'created_at',
                'updated_at',
            ])
            ->with([
                'user:id,name,email,status',
                'schedules' => fn ($query) => $query
                    ->select('id', 'professional_id', 'location_id', 'location_name', 'weekday', 'start_time', 'end_time')
                    ->orderBy('weekday')
                    ->orderBy('start_time'),
                'schedules.location:id,name,code',
            ])
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(
                    fn (Builder $nestedQuery) => $nestedQuery
                        ->where('document_number', 'like', "%$search%")
                        ->orWhere('specialty', 'like', "%$search%")
                        ->orWhere('registration_number', 'like', "%$search%")
                        ->orWhereHas(
                            'user',
                            fn (Builder $userQuery) => $userQuery
                                ->where('name', 'like', "%$search%")
                                ->orWhere('email', 'like', "%$search%"),
                        ),
                ),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status),
            )
            ->when(
                $filters['specialty'] ?? null,
                fn (Builder $query, string $specialty) => $query->where('specialty', $specialty),
            )
            ->latest('id')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Professional
    {
        return Professional::query()
            ->with([
                'user:id,name,email,status,invitation_accepted_at',
                'schedules' => fn ($query) => $query
                    ->orderBy('weekday')
                    ->orderBy('start_time'),
                'schedules.location:id,name,code',
            ])
            ->findOrFail($id);
    }

    /**
     * @return array{total: int, active: int, inactive: int, on_leave: int, invitation_pending: int}
     */
    public function summaries(): array
    {
        $countsByStatus = Professional::query()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $countsByStatus->sum(),
            'active' => (int) $countsByStatus->get(ProfessionalStatus::Active->value, 0),
            'inactive' => (int) $countsByStatus->get(ProfessionalStatus::Inactive->value, 0),
            'on_leave' => (int) $countsByStatus->get(ProfessionalStatus::OnLeave->value, 0),
            'invitation_pending' => Professional::query()
                ->whereHas(
                    'user',
                    fn (Builder $query) => $query->where('status', UserStatus::PendingInvitation->value),
                )
                ->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{location_id: int, location_name: string, weekday: int, start_time: string, end_time: string}>  $schedules
     */
    public function create(array $attributes, array $schedules): Professional
    {
        $professional = Professional::query()->create($attributes);
        $professional->schedules()->createMany($schedules);

        return $professional->load([
            'user:id,name,email,status,invitation_accepted_at',
            'schedules' => fn ($query) => $query
                ->orderBy('weekday')
                ->orderBy('start_time'),
            'schedules.location:id,name,code',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{location_id: int, location_name: string, weekday: int, start_time: string, end_time: string}>  $schedules
     */
    public function update(Professional $professional, array $attributes, array $schedules): Professional
    {
        $professional->update($attributes);
        $professional->schedules()->delete();
        $professional->schedules()->createMany($schedules);

        return $professional->refresh()->load([
            'user:id,name,email,status,invitation_accepted_at',
            'schedules' => fn ($query) => $query
                ->orderBy('weekday')
                ->orderBy('start_time'),
            'schedules.location:id,name,code',
        ]);
    }

    public function setStatus(Professional $professional, ProfessionalStatus $status): Professional
    {
        $professional->update(['status' => $status]);

        return $professional->refresh()->load('user:id,name,email,status,invitation_accepted_at');
    }
}
