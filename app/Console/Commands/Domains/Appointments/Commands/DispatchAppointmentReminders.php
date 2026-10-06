<?php

namespace App\Console\Commands\Domains\Appointments\Commands;

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Appointments\Models\AppointmentReminderDelivery;
use App\Jobs\Domains\Appointments\Jobs\SendAppointmentReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('appointments:dispatch-reminders')]
#[Description('Encola recordatorios administrativos de turnos próximos')]
class DispatchAppointmentReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queued = 0;

        Appointment::query()
            ->with(['patient.notificationPreference'])
            ->whereIn('status', ['reserved', 'confirmed'])
            ->whereBetween('starts_at', [now(), now()->addHours(48)])
            ->orderBy('id')
            ->chunkById(100, function ($appointments) use (&$queued): void {
                foreach ($appointments as $appointment) {
                    $preference = $appointment->patient->notificationPreference;
                    $hours = $preference?->appointment_reminder_hours ?? 24;

                    if (($preference?->appointment_reminders_by_email ?? true) && now()->gte($appointment->starts_at->subHours($hours))) {
                        $delivery = AppointmentReminderDelivery::query()->firstOrCreate(
                            ['appointment_id' => $appointment->getKey(), 'channel' => 'email'],
                            ['status' => 'queued', 'queued_at' => now()],
                        );

                        if ($delivery->wasRecentlyCreated) {
                            SendAppointmentReminder::dispatch((int) $delivery->getKey())->afterCommit();
                            $queued++;
                        }
                    }
                }
            });

        $this->info("Se encolaron {$queued} recordatorios.");

        return self::SUCCESS;
    }
}
