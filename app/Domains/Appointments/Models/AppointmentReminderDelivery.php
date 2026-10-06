<?php

namespace App\Domains\Appointments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentReminderDelivery extends Model
{
    use HasFactory;

    protected $fillable = ['appointment_id', 'channel', 'status', 'queued_at', 'delivered_at', 'failed_at', 'attempts', 'failure_reason'];

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'delivered_at' => 'datetime', 'failed_at' => 'datetime', 'attempts' => 'integer'];
    }
}
