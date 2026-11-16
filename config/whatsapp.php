<?php

return [
    'enabled' => env('WHATSAPP_REMINDERS_ENABLED', false),
    'sender' => env('WHATSAPP_SENDER'),
    'template' => env('WHATSAPP_REMINDER_TEMPLATE', 'appointment_reminder'),
];
