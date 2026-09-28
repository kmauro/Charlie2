<?php

namespace App\Domains\Appointments\Models;

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Enums\AppointmentType;
use App\Domains\Appointments\Policies\AppointmentPolicy;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Models\ConsultingRoom;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Users\Models\User;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[UsePolicy(AppointmentPolicy::class)]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'professional_id',
        'location_id',
        'consulting_room_id',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'type',
        'status',
        'office',
        'reason',
        'is_overbook',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'lock_version',
    ];

    protected $hidden = [
        'reason',
        'cancellation_reason',
    ];

    protected $attributes = [
        'status' => AppointmentStatus::Reserved->value,
        'is_overbook' => false,
        'lock_version' => 1,
    ];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<ConsultingRoom, $this> */
    public function consultingRoom(): BelongsTo
    {
        return $this->belongsTo(ConsultingRoom::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (Appointment $appointment): void {
            if ($appointment->is_overbook) {
                throw new LogicException(
                    'Los sobreturnos todavía no están habilitados.',
                );
            }
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Los turnos no pueden eliminarse físicamente; deben cancelarse.',
            );
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_minutes' => 'integer',
            'type' => AppointmentType::class,
            'status' => AppointmentStatus::class,
            'is_overbook' => 'boolean',
            'cancelled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }
}
