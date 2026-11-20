<?php

namespace App\Domains\Auth\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaFactor extends Model
{
    protected $table = 'user_mfa_factors';

    protected $fillable = ['user_id', 'secret', 'confirmed_at', 'last_accepted_step', 'revoked_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'confirmed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null && $this->revoked_at === null;
    }
}
