<?php

namespace App\Domains\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $token,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Restablecer contraseña')
            ->greeting('Hola, '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $url)
            ->line('El enlace vence en 60 minutos y sólo puede utilizarse una vez.')
            ->line('Si no realizaste esta solicitud, podés ignorar este correo.');
    }
}
