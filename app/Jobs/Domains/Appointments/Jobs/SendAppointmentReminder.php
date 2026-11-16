<?php

namespace App\Jobs\Domains\Appointments\Jobs;

use App\Domains\Appointments\Models\AppointmentReminderDelivery;
use App\Domains\Appointments\Contracts\WhatsAppProvider;
use App\Domains\Appointments\Enums\AppointmentStatus;
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
    public function handle(WhatsAppProvider $whatsApp): void
    {
        $delivery = AppointmentReminderDelivery::query()
            ->with(['appointment.patient', 'appointment.professional.user', 'appointment.location'])
            ->findOrFail($this->deliveryId);

        if ($delivery->status === 'delivered' || ! in_array($delivery->appointment->status, [AppointmentStatus::Reserved, AppointmentStatus::Confirmed], true)) {
            return;
        }

        $appointment = $delivery->appointment;
        if ($delivery->channel === 'whatsapp') {
            $preference = $appointment->patient->notificationPreference;
            if (! config('whatsapp.enabled') || ! $preference?->appointment_reminders_by_whatsapp || $preference->whatsapp_consented_at === null || $preference->whatsapp_revoked_at !== null || ! filled($preference->whatsapp_phone)) return;
            $result = $whatsApp->sendReminder($preference->whatsapp_phone, (string) $delivery->idempotency_key);
            $delivery->update(['status' => 'delivered', 'accepted_at' => now(), 'delivered_at' => now(), 'provider_message_id' => $result['provider_message_id'], 'attempts' => $delivery->attempts + 1, 'failure_reason' => null]);
            return;
        }
        Mail::to($appointment->patient->email)->send(new AppointmentReminderMail(patientName: trim($appointment->patient->first_name.' '.$appointment->patient->last_name), professionalName: $appointment->professional->user->name, locationName: $appointment->location->name, startsAt: $appointment->starts_at->translatedFormat('l d/m/Y H:i')));

        $delivery->update(['status' => 'delivered', 'delivered_at' => now(), 'attempts' => $delivery->attempts + 1, 'failure_reason' => null]);
    }

    public function failed(\Throwable $exception): void
    {
        AppointmentReminderDelivery::query()->whereKey($this->deliveryId)->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => str($exception->getMessage())->limit(500), 'attempts' => \DB::raw('attempts + 1')]);
    }
}
