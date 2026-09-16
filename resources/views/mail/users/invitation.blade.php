<x-mail::message>
# Te invitaron a {{ config('app.name') }}

Hola {{ $recipientName }}:

Crearon una cuenta para vos. Usá el siguiente enlace para completar tus datos y elegir una contraseña.

<x-mail::button :url="$invitationUrl">
Completar registro
</x-mail::button>

El enlace vence el {{ $expiresAt->setTimezone(config('app.timezone'))->format('d/m/Y \a \l\a\s H:i') }} y puede utilizarse una sola vez.

Si no esperabas esta invitación, podés ignorar este mensaje.

Saludos,<br>
{{ config('app.name') }}
</x-mail::message>
