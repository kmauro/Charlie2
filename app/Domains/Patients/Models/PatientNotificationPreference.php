<?php

namespace App\Domains\Patients\Models;

use Illuminate\Database\Eloquent\Model;

class PatientNotificationPreference extends Model
{
    protected $fillable = ['patient_id', 'appointment_reminders_by_email', 'appointment_reminders_by_whatsapp', 'appointment_reminder_hours'];

    protected function casts(): array
    {
        return ['appointment_reminders_by_email' => 'boolean', 'appointment_reminders_by_whatsapp' => 'boolean', 'appointment_reminder_hours' => 'integer'];
    }
}
