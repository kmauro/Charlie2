<?php

namespace App\Domains\Appointments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentSeries extends Model
{
    protected $fillable = [
        'patient_id', 'professional_id', 'location_id', 'consulting_room_id', 'type',
        'frequency', 'ends_on', 'occurrences', 'duration_minutes', 'procedure_resources',
        'procedure_preparation',
    ];

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    protected function casts(): array
    {
        return ['ends_on' => 'date', 'procedure_resources' => 'array'];
    }
}
