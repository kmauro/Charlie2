<?php

namespace App\Console\Commands\Domains\Appointments\Commands;

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Models\Appointment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('appointments:cancel-expired')]
#[Description('Cancela turnos que superaron 24 horas desde su inicio')]
class CancelExpiredAppointments extends Command
{
    public function handle(): int
    {
        $cancelled = Appointment::query()
            ->where('starts_at', '<=', now()->subHours(24))
            ->whereIn('status', [
                AppointmentStatus::Reserved->value,
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Waiting->value,
                AppointmentStatus::Present->value,
            ])
            ->update([
                'status' => AppointmentStatus::Cancelled->value,
                'cancellation_reason' => 'Cancelado automáticamente al superar 24 horas desde su inicio.',
                'cancelled_at' => now(),
                'cancelled_by' => null,
                'lock_version' => DB::raw('lock_version + 1'),
            ]);

        $this->info("Se cancelaron {$cancelled} turnos vencidos.");

        return self::SUCCESS;
    }
}
