<?php

namespace App\Jobs\Domains\Appointments\Jobs;

use App\Domains\Appointments\Models\AppointmentReminderDelivery;
use App\Mail\Domains\Appointments\Mail\AppointmentReminderMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public function __construct(public int $deliveryId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $delivery = AppointmentReminderDelivery::query()
            ->with(['appointment.patient', 'appointment.professional.user', 'appointment.location'])
            ->findOrFail($this->deliveryId);

        if ($delivery->status === 'delivered') {
            return;
        }

        $appointment = $delivery->appointment;
        Mail::to($appointment->patient->email)->send(new AppointmentReminderMail(
            patientName: trim($appointment->patient->first_name.' '.$appointment->patient->last_name),
            professionalName: $appointment->professional->user->name,
            locationName: $appointment->location->name,
            startsAt: $appointment->starts_at->translatedFormat('l d/m/Y H:i'),
        ));

        $delivery->update(['status' => 'delivered', 'delivered_at' => now(), 'attempts' => $delivery->attempts + 1, 'failure_reason' => null]);
    }

    public function failed(\Throwable $exception): void
    {
        AppointmentReminderDelivery::query()->whereKey($this->deliveryId)->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => str($exception->getMessage())->limit(500), 'attempts' => \DB::raw('attempts + 1')]);
    }
}
