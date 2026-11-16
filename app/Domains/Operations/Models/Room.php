<?php

namespace App\Domains\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = ['ward_id', 'code', 'name', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function ward(): BelongsTo { return $this->belongsTo(Ward::class); }
    public function beds(): HasMany { return $this->hasMany(Bed::class); }
}
