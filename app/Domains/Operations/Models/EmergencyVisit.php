<?php

namespace App\Domains\Operations\Models;

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Operations\Enums\EmergencyVisitStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmergencyVisit extends Model
{
    protected $fillable = ['patient_id', 'location_id', 'appointment_id', 'status', 'priority', 'arrived_at', 'started_at', 'closed_at', 'outcome', 'cancellation_reason', 'created_by', 'started_by', 'closed_by', 'lock_version'];
    protected $attributes = ['status' => EmergencyVisitStatus::Waiting->value, 'lock_version' => 1];
    protected function casts(): array { return ['status' => EmergencyVisitStatus::class, 'arrived_at' => 'datetime', 'started_at' => 'datetime', 'closed_at' => 'datetime', 'priority' => 'integer', 'lock_version' => 'integer']; }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function appointment(): BelongsTo { return $this->belongsTo(Appointment::class); }
    public function admission(): HasOne { return $this->hasOne(Admission::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
