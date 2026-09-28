<?php

namespace App\Domains\Professionals\Models;

use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Enums\ScheduleExceptionType;
use Database\Factories\ProfessionalScheduleExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalScheduleException extends Model
{
    /** @use HasFactory<ProfessionalScheduleExceptionFactory> */
    use HasFactory;

    protected $fillable = ['professional_id', 'location_id', 'date', 'type', 'start_time', 'end_time', 'reason'];

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

    protected static function newFactory(): ProfessionalScheduleExceptionFactory
    {
        return ProfessionalScheduleExceptionFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date', 'type' => ScheduleExceptionType::class];
    }
}
