<?php

namespace App\Domains\Billing\Models;

use Database\Factories\Domains\Billing\Models\FiscalConfigurationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalConfiguration extends Model
{
    /** @use HasFactory<FiscalConfigurationFactory> */
    use HasFactory;

    protected $fillable = ['legal_entity_id', 'arca_enabled', 'service', 'arca_certificate', 'arca_private_key'];

    protected $hidden = ['arca_certificate', 'arca_private_key'];

    protected function casts(): array
    {
        return ['arca_enabled' => 'boolean', 'arca_certificate' => 'encrypted', 'arca_private_key' => 'encrypted'];
    }
}
