# Operación y mantenimiento

[Cierta] Referencia técnica al 7 de septiembre de 2026. Instalación inicial: [README](README.md). Estado y tareas pendientes: [TRASPASO](TRASPASO.md).

## Configuración que debe transferirse

[Cierta] Variables verificadas en `.env.example` y archivos de `config/`; no se incluyen valores privados del entorno:

| Variables | Función y cuidado operativo |
| --- | --- |
| `APP_NAME`, `VITE_APP_NAME` | Nombre configurado. También hay textos «Clínica» en el frontend; cambiar variables no sustituye todos los textos. |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Entorno, depuración y dirección utilizada para enlaces. |
| `APP_KEY` | Clave de cifrado, incluida auditoría. Conservar al migrar o restaurar una instalación. |
| `APP_TIMEZONE` | Plantilla: `America/Argentina/Buenos_Aires`. Revisar coherencia con horarios de sede y turnos. |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | Plantilla: `es_AR`, `es`, `es_AR`. |
| `DB_*` | Conexión MySQL. La plantilla trae valores de desarrollo, no credenciales productivas. |
| `SUPERADMIN_*` | Bootstrap mediante seeder. Reejecutarlo modifica la cuenta configurada, incluida contraseña. |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_DOMAIN` | Plantilla: base de datos, 120 minutos y cifrado de sesión desactivado. |
| `CACHE_STORE`, `QUEUE_CONNECTION` | Ambos usan base de datos en la plantilla; requieren sus tablas. |
| `MAIL_*` | Transporte y remitente. `MAIL_MAILER=log` no entrega mensajes. |
| `EXTERNAL_CLINICAL_RECORD_URL` | Destino HTTPS; se lee desde `services.external_clinical_record.url`. |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL` | Plantilla: stack/single/debug; registros bajo `storage/logs`. |
| `FILESYSTEM_DISK`, `AWS_*`, `REDIS_*` | Configuración de infraestructura disponible; no acredita una integración de negocio en uso. |

[Suposición] Transferir secretos mediante el gestor privado acordado por el equipo, con acceso para la persona entrante. Completar inventario de entornos, responsables y vencimientos en la lista de traspaso; no agregar contraseñas, claves ni enlaces de invitación activos a estos documentos.

## Puesta en marcha de un entorno existente

[Suposición] Procedimiento de referencia que debe adaptarse al hosting real, todavía no identificado en esta revisión:

1. Identificar versión/copia de código que se va a ejecutar y compatibilidad con la base.
2. Instalar desde `composer.lock` y `package-lock.json`.
3. Incorporar configuración privada y `APP_KEY` originales si la base ya tiene datos cifrados.
4. Configurar servidor web con raíz en `public/` y permisos de escritura para `storage/` y `bootstrap/cache/`.
5. Revisar migraciones pendientes antes de aplicarlas; disponer de backup restaurable.
6. Compilar frontend y probar acceso, autorizaciones y flujos con datos ficticios en un entorno separado.

## Despliegue de una versión

[Cierta] No se encontró pipeline `.github`, configuración Docker ni procedimiento específico del proveedor en esta copia. La revisión no identifica un entorno de producción.

[Suposición] Secuencia propuesta para un despliegue controlado; no fue ejecutada contra producción:

1. Validar pruebas y frontend. La suite actual tiene un fallo conocido; resolverlo o acordar explícitamente su tratamiento antes de liberar.
2. Respaldar base, configuración privada, claves, archivos persistentes y versión anterior.
3. Preparar la nueva versión con `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`, `npm ci` y `npm run build` en el entorno de construcción.
4. Configurar `APP_ENV=production`, `APP_DEBUG=false`, URL real con HTTPS, correo real y destino clínico acordado.
5. En una ventana controlada, ejecutar los comandos siguientes desde la raíz de la versión y con la configuración correcta.

```powershell
php artisan down --no-interaction
php artisan migrate --force --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan queue:restart --no-interaction
php artisan up --no-interaction
```

[Suposición] Si un paso falla, detener la secuencia y evaluar recuperación; no continuar automáticamente hasta `up`. Reiniciar procesos persistentes según el supervisor real. No ejecutar `db:seed`, `key:generate` ni `migrate:fresh` como parte de una actualización ordinaria.

[Cierta] `bootstrap/app.php` declara comprobación de salud `/up`. El script de desarrollo inicia una escucha de cola, pero las invitaciones actuales se envían sincrónicamente. `routes/console.php` solo contiene el comando de ejemplo `inspire`; no hay tareas de negocio programadas allí.

[Suposición] Después del despliegue, comprobar `/up`, login, listados, creación/edición de un caso ficticio en un entorno de validación y correo de invitación al destinatario controlado. Verificar también denegaciones por rol y acceso clínico con el proveedor. Una respuesta de `/up` no sustituye esas comprobaciones funcionales.

## Backup y recuperación

[Cierta] Los snapshots de auditoría están cifrados con casts de Laravel. Una copia de la base sin la clave correspondiente no basta para recuperarlos.

[Suposición] Definir con la persona responsable: frecuencia y retención de backups, ubicación, cifrado, quién restaura, pérdida máxima de datos aceptable y tiempo máximo de recuperación. Incluir base MySQL, `APP_KEY` y configuración privada, archivos persistentes de `storage/app` si existen y artefacto de código/lockfiles correspondiente.

[Suposición] Ensayar restauración en un entorno aislado: restaurar base y archivos, recuperar la clave original, comprobar descifrado de auditoría con una cuenta autorizada, acceso y relaciones entre entidades. Registrar fecha y resultado del ensayo. No enviar mensajes reales durante el ensayo.

[Suposición] Para revertir una versión, decidir primero si el esquema sigue siendo compatible con el código anterior. Un rollback de código no revierte datos. Si se necesita restaurar backup, acordar el tratamiento de las escrituras posteriores antes de reemplazar la base; no ejecutar `migrate:rollback` indiscriminadamente.

## Diagnóstico frecuente

[Cierta] Causas y comprobaciones vinculadas al código/configuración actual:

| Síntoma | Comprobación o acción |
| --- | --- |
| `npm` no reconocido | Instalar/habilitar Node con npm en el PATH y abrir una terminal nueva. Con dependencias ya instaladas, las comprobaciones puntuales pueden ejecutarse directamente con Node, como se hizo en el traspaso. |
| Manifest de Vite inexistente o recursos ausentes | Ejecutar `npm run build` o mantener `npm run dev` activo. Revisar `public/hot` si apunta a un servidor de desarrollo detenido. |
| Error de conexión o tabla inexistente | Revisar `DB_*`, acceso al servicio y `php artisan migrate:status --no-interaction`; revisar también registro de proveedores de dominio. |
| Seeder rechaza credenciales | Completar `SUPERADMIN_*`; contraseña mínima de 12 caracteres con mayúsculas, minúsculas, números y símbolos. |
| Invitación no llega | `MAIL_MAILER=log` registra, no envía. Con SMTP, revisar configuración y error de la petición; la cola no resuelve ese envío síncrono. |
| Invitación inválida | Revisar vencimiento, consumo previo, reinvitación y coincidencia de URL/esquema. La firma y el token no deben modificarse. |
| Login rechazado | Revisar credenciales, cuenta pendiente/suspendida, rol y limitación de intentos. |
| 403 en un módulo | Consultar la matriz de policies en `ARQUITECTURA.md`; asignar un permiso individual no cambia esas policies. |
| 403 en acceso clínico | Comprobar rol profesional, perfil activo y existencia del turno admitido por la regla de relación asistencial. |
| 503 en acceso clínico | Revisar `EXTERNAL_CLINICAL_RECORD_URL`: válida y HTTPS. Una URL válida no garantiza disponibilidad externa. |
| Error de versión al guardar | Otra edición cambió `lock_version`; recargar, revisar cambios y volver a aplicar la edición. |
| No se permite turno/cancelación | Revisar estados, horario futuro, agenda/sede, superposiciones, versión y anticipación mínima de 24 horas. |
| Dashboard sigue en cero | Es el comportamiento fijo actual, no un indicador fiable de la base. |
| No se descifra auditoría restaurada | Verificar que la instalación usa la clave original; generar una nueva no recupera el contenido previo. |

[Cierta] La caché de configuración puede mantener valores anteriores de `.env`. En desarrollo puede limpiarse con `php artisan config:clear --no-interaction`; en un despliegue, reconstruirla dentro del procedimiento de la versión. Los logs pueden contener datos personales y enlaces de invitación cuando se usa correo `log`; no compartirlos sin revisar y ocultar esos datos.
