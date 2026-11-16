<?php

namespace App\Domains\Operations\Models;

use App\Domains\Organizations\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    protected $fillable = ['location_id', 'code', 'name', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function rooms(): HasMany { return $this->hasMany(Room::class); }
}
