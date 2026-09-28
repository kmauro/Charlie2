<?php

namespace App\Domains\Professionals\Models;

use App\Domains\Organizations\Models\Location;
use Database\Factories\ProfessionalLeaveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalLeave extends Model
{
    /** @use HasFactory<ProfessionalLeaveFactory> */
    use HasFactory;

    protected $fillable = ['professional_id', 'location_id', 'starts_on', 'ends_on', 'reason'];

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

    protected static function newFactory(): ProfessionalLeaveFactory
    {
        return ProfessionalLeaveFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }
}
