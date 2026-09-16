<?php

namespace App\Domains\Professionals\Models;

use App\Domains\Professionals\Database\Factories\ProfessionalFactory;
use App\Domains\Professionals\Enums\OfficeAssignmentType;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Policies\ProfessionalPolicy;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(ProfessionalPolicy::class)]
class Professional extends Model
{
    /** @use HasFactory<ProfessionalFactory> */
    use HasFactory;

    protected $fillable = [
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
    ];

    protected $attributes = [
        'status' => ProfessionalStatus::Active->value,
        'office_assignment_type' => OfficeAssignmentType::Availability->value,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ProfessionalSchedule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProfessionalStatus::Active->value);
    }

    protected static function newFactory(): ProfessionalFactory
    {
        return ProfessionalFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'appointment_duration_minutes' => 'integer',
            'status' => ProfessionalStatus::class,
            'office_assignment_type' => OfficeAssignmentType::class,
        ];
    }
}
