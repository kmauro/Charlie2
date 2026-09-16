<?php

namespace App\Domains\Audits\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'event',
        'auditable_type',
        'auditable_id',
        'before_values',
        'after_values',
        'outcome',
        'reason',
        'correlation_id',
        'ip_address',
        'user_agent',
    ];

    protected $hidden = [
        'before_values',
        'after_values',
        'reason',
    ];

    protected $attributes = [
        'outcome' => 'success',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function performUpdate(Builder $query): never
    {
        throw new LogicException('Los registros de auditoría son inmutables.');
    }

    protected function performDeleteOnModel(): never
    {
        throw new LogicException(
            'Los registros de auditoría no pueden eliminarse físicamente.',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_values' => 'encrypted:array',
            'after_values' => 'encrypted:array',
            'reason' => 'encrypted',
            'created_at' => 'immutable_datetime',
        ];
    }
}
