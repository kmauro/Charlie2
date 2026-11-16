<?php

namespace App\Domains\Operations\Models;

use App\Domains\Operations\Enums\AdmissionStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admission extends Model
{
    protected $fillable = ['patient_id', 'location_id', 'emergency_visit_id', 'status', 'admitted_at', 'discharged_at', 'admitted_by', 'discharged_by', 'lock_version'];
    protected $attributes = ['status' => AdmissionStatus::Active->value, 'lock_version' => 1];
    protected function casts(): array { return ['status' => AdmissionStatus::class, 'admitted_at' => 'datetime', 'discharged_at' => 'datetime', 'lock_version' => 'integer']; }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function emergencyVisit(): BelongsTo { return $this->belongsTo(EmergencyVisit::class); }
    public function assignments(): HasMany { return $this->hasMany(BedAssignment::class); }
}
