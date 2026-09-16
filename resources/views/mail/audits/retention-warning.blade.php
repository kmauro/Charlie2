<x-mail::message>
# Limpieza del registro de auditoría

Se eliminarán {{ $recordsCount }} registros anteriores al {{ $cutoff->format('d/m/Y H:i') }} por haber superado el período de conservación de un año.

Este aviso fue enviado inmediatamente antes de ejecutar la limpieza automática.

{{ config('app.name') }}
</x-mail::message>
