<?php

namespace App\Domains\Professionals\Models;

use App\Domains\Organizations\Models\Location;
use Database\Factories\ConsultingRoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsultingRoom extends Model
{
    /** @use HasFactory<ConsultingRoomFactory> */
    use HasFactory;

    protected $fillable = [
        'location_id',
        'name',
        'code',
        'sector',
        'floor',
        'equipment',
        'operational_restrictions',
        'is_active',
    ];

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<ProfessionalSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(ProfessionalSchedule::class);
    }

    protected static function newFactory(): ConsultingRoomFactory
    {
        return ConsultingRoomFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['equipment' => 'array', 'is_active' => 'boolean'];
    }
}
