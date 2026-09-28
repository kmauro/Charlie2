<?php

namespace App\Domains\Professionals\Models;

use App\Domains\Organizations\Models\Location;
use Database\Factories\LocationHolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationHoliday extends Model
{
    /** @use HasFactory<LocationHolidayFactory> */
    use HasFactory;

    protected $fillable = ['location_id', 'date', 'name'];

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    protected static function newFactory(): LocationHolidayFactory
    {
        return LocationHolidayFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
