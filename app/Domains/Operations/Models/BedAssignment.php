<?php

namespace App\Domains\Operations\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BedAssignment extends Model
{
    protected $fillable = ['admission_id', 'bed_id', 'started_at', 'ended_at', 'assigned_by', 'ended_by', 'received_at', 'received_by'];
    protected function casts(): array { return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'received_at' => 'datetime']; }
    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function bed(): BelongsTo { return $this->belongsTo(Bed::class); }
    public function receivedBy(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
}
