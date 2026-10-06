<x-mail::message>
# Recordatorio de turno

Hola, {{ $patientName }}. Te recordamos que tenés un turno el {{ $startsAt }}.

Profesional: {{ $professionalName }}  
Sede: {{ $locationName }}

Si necesitás reprogramarlo o cancelarlo, ingresá a tu portal con anticipación.

Saludos,<br>
{{ config('app.name') }}
</x-mail::message>
