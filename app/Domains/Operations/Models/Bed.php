<?php

namespace App\Domains\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bed extends Model
{
    protected $fillable = ['room_id', 'code', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function assignments(): HasMany { return $this->hasMany(BedAssignment::class); }
}
