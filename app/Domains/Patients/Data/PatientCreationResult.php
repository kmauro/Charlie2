<?php

namespace App\Domains\Patients\Data;

use App\Domains\Patients\Models\Patient;

final readonly class PatientCreationResult
{
    public function __construct(
        public Patient $patient,
        public string $invitationUrl,
    ) {}
}
