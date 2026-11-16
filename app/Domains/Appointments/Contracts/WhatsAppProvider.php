<?php

namespace App\Domains\Appointments\Contracts;

interface WhatsAppProvider
{
    /** @return array{provider_message_id: string} */
    public function sendReminder(string $recipient, string $idempotencyKey): array;
}
