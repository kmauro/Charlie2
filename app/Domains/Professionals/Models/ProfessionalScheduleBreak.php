<?php

namespace App\Domains\Professionals\Models;

use Database\Factories\ProfessionalScheduleBreakFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalScheduleBreak extends Model
{
    /** @use HasFactory<ProfessionalScheduleBreakFactory> */
    use HasFactory;

    protected $fillable = ['professional_schedule_id', 'start_time', 'end_time', 'label'];

    /** @return BelongsTo<ProfessionalSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ProfessionalSchedule::class, 'professional_schedule_id');
    }

    protected static function newFactory(): ProfessionalScheduleBreakFactory
    {
        return ProfessionalScheduleBreakFactory::new();
    }
}
