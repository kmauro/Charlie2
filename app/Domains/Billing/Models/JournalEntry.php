<?php

namespace App\Domains\Billing\Models;

use Database\Factories\Domains\Billing\Models\JournalEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    protected $fillable = ['legal_entity_id', 'created_by', 'source_type', 'source_id', 'entry_date', 'description'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }
}
