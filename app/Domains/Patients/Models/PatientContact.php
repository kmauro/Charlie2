<?php

namespace App\Domains\Patients\Models;

use Database\Factories\PatientContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientContact extends Model
{
    /** @use HasFactory<PatientContactFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'full_name',
        'relationship',
        'phone',
        'email',
        'is_emergency_contact',
        'is_legal_representative',
        'is_financially_responsible',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    protected static function newFactory(): PatientContactFactory
    {
        return PatientContactFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_emergency_contact' => 'boolean',
            'is_legal_representative' => 'boolean',
            'is_financially_responsible' => 'boolean',
        ];
    }
}
