<?php

namespace App\Domains\Billing\Models;

use Database\Factories\Domains\Billing\Models\JournalEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    protected $fillable = ['legal_entity_id', 'created_by', 'source_type', 'source_id', 'entry_date', 'description'];

    /** @return HasMany<JournalEntryLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }
}
