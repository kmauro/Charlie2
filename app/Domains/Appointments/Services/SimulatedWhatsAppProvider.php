<?php

namespace App\Domains\Appointments\Services;

use App\Domains\Appointments\Contracts\WhatsAppProvider;

class SimulatedWhatsAppProvider implements WhatsAppProvider
{
    public function sendReminder(string $recipient, string $idempotencyKey): array
    {
        return ['provider_message_id' => 'simulated-'.$idempotencyKey];
    }
}
