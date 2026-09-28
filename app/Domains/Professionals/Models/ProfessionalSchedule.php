<?php

namespace App\Domains\Professionals\Models;

use App\Domains\Organizations\Models\Location;
use Database\Factories\ProfessionalScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfessionalSchedule extends Model
{
    /** @use HasFactory<ProfessionalScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'location_name',
        'location_id',
        'consulting_room_id',
        'weekday',
        'start_time',
        'end_time',
    ];

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

    /** @return HasMany<ProfessionalScheduleBreak, $this> */
    public function breaks(): HasMany
    {
        return $this->hasMany(ProfessionalScheduleBreak::class);
    }

    protected static function newFactory(): ProfessionalScheduleFactory
    {
        return ProfessionalScheduleFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location_id' => 'integer',
            'consulting_room_id' => 'integer',
            'weekday' => 'integer',
        ];
    }
}
