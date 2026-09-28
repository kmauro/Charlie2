<?php

namespace App\Domains\Billing\Models;

use Database\Factories\Domains\Billing\Models\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $fillable = ['legal_entity_id', 'code', 'name', 'type', 'is_system', 'is_active'];
}
