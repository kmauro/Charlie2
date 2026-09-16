<?php

namespace App\Domains\Professionals\Repositories\Contracts;

use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\Professional;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProfessionalRepository
{
    /**
     * @param  array{search?: string, status?: string, specialty?: string}  $filters
     * @return LengthAwarePaginator<int, Professional>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    public function findOrFail(int $id): Professional;

    /**
     * @return array{total: int, active: int, inactive: int, on_leave: int, invitation_pending: int}
     */
    public function summaries(): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{location_name: string, weekday: int, start_time: string, end_time: string}>  $schedules
     */
    public function create(array $attributes, array $schedules): Professional;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{location_name: string, weekday: int, start_time: string, end_time: string}>  $schedules
     */
    public function update(Professional $professional, array $attributes, array $schedules): Professional;

    public function setStatus(Professional $professional, ProfessionalStatus $status): Professional;
}
