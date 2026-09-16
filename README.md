# Charlie2 · HIS administrativo

[Cierta] Aplicación para administrar una clínica privada con varias sedes y razones sociales. Incluye usuarios por invitación, profesionales, pacientes, organización y turnos. La información clínica pertenece a una webapp externa; este sistema ofrece un enlace autorizado y auditado.

## Documentación de traspaso

[Cierta] Documentación contrastada con el código el **7 de septiembre de 2026**. Orden de lectura:

1. [Traspaso y estado actual](TRASPASO.md): verificaciones, pendientes y datos que debe entregar la persona saliente.
2. Este README: instalación y comandos cotidianos.
3. [Arquitectura y reglas funcionales](ARQUITECTURA.md): dominios, datos, permisos y desarrollo.
4. [Operación y mantenimiento](OPERACION.md): configuración, despliegue, recuperación y diagnóstico.
5. [Plan del proyecto](PLAN_PROYECTO.md): objetivos de producto, incluidos requisitos futuros.
6. [Registro de cambios](CHANGELOG.md): evolución cronológica reconstruida desde los chats y el código disponible.
7. [Instrucciones de desarrollo](AGENTS.md): convenciones del proyecto.

[Cierta] En estos documentos, `[Cierta]` identifica hechos comprobados en archivos o comandos; `[Probable]`, inferencias; y `[Suposición]`, propuestas o información pendiente de confirmación. Una etiqueta antes de una tabla o lista se aplica al conjunto.

## Requisitos

[Cierta] Base tecnológica y referencias locales:

| Componente | Referencia |
| --- | --- |
| PHP | Objetivo: 8.5; verificado con 8.5.1. Aunque el manifiesto raíz declara `^8.3`, deben cumplirse todos los requisitos del lockfile. |
| Composer | Verificado con 2.9.5; conservar `composer.lock`. |
| Laravel / Inertia Laravel | Restricciones `^13.8` / `^3.1` en `composer.json`. |
| Frontend | Vue 3, TypeScript 6, Tailwind 4, Vite 8; versiones resueltas en `package-lock.json`. |
| Node.js | Vite instalado exige `^20.19.0 || >=22.12.0`; verificado con 24.19.0. |
| npm | Necesario para instalar dependencias y ejecutar scripts. |
| Base de datos | MySQL en `.env.example`; SQLite en memoria para pruebas. |
| Extensiones PHP | Verificar con `composer check-platform-reqs`; además, PDO MySQL para la aplicación y PDO SQLite para pruebas. |

## Instalación local desde cero

[Cierta] Ejecutar en la raíz de una copia nueva. Usar los lockfiles para conservar las versiones:

```powershell
composer install --no-interaction
Copy-Item .env.example .env
npm ci
```

[Cierta] Copiar `.env.example` solo si no existe `.env`; conservar la configuración si se recibe un entorno preparado. `.npmrc` establece `ignore-scripts=true`.

[Cierta] Crear una base MySQL vacía y completar `.env` antes de migrar:

- `APP_URL`: dirección real del entorno, coincidente con la usada para abrir invitaciones.
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- `SUPERADMIN_NAME`, `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD`: no existen credenciales predeterminadas.
- `MAIL_MAILER=log` para desarrollo: registra los mensajes localmente, no los entrega por correo.
- `EXTERNAL_CLINICAL_RECORD_URL`: la plantilla trae un destino de ejemplo, no una integración operativa.

[Cierta] La contraseña inicial requiere al menos 12 caracteres, mayúsculas, minúsculas, números y símbolos. Para una base nueva:

```powershell
php artisan key:generate --no-interaction
php artisan migrate --no-interaction
php artisan db:seed --no-interaction
npm run build
composer run dev
```

[Cierta] El seeder carga roles y crea o actualiza el superadministrador; no carga pacientes ni turnos de demostración. Reejecutarlo también actualiza contraseña y estado de esa cuenta. No usarlo como tarea rutinaria de despliegue.

[Cierta] `key:generate` es para una instalación nueva. Para restaurar una base existente se necesita su `APP_KEY`, utilizada para descifrar auditoría. `migrate:fresh` elimina tablas y no forma parte de esta instalación.

[Cierta] `composer run dev` inicia servidor PHP, escucha de cola y Vite. Con Laragon se puede usar su servidor web, apuntado a `public/`, y ejecutar `npm run dev` por separado. Abrir la dirección configurada en `APP_URL`, ruta `/login`, con las credenciales iniciales.

[Cierta] `composer run setup` genera una clave, migra con `--force`, ejecuta `npm install --ignore-scripts` y compila; no ejecuta el seeder. La secuencia explícita anterior permite preparar la configuración antes de esas operaciones.

## Comandos habituales

[Cierta] Comprobaciones disponibles:

```powershell
php artisan route:list --except-vendor --no-interaction
php artisan migrate:status --no-interaction
composer check-platform-reqs
npm run typecheck
npm run build
php artisan test --compact
php artisan test --compact --filter=AppointmentManagementTest
```

[Cierta] Si se modifica PHP, ejecutar el formateador indicado por `AGENTS.md`:

```powershell
php vendor/bin/pint --dirty --format agent
```

[Cierta] `phpunit.xml` configura SQLite `:memory:`, correo `array`, sesión/caché `array` y cola `sync`. Comprobar que ninguna configuración cacheada o variable externa altere ese aislamiento antes de probar. No ejecutar pruebas contra la base de trabajo.

[Cierta] Verificación del traspaso: **147 de 148 pruebas aprobadas**, con un fallo conocido del dashboard. Typecheck y build aprobados. Detalles en [TRASPASO.md](TRASPASO.md).

## Primera carga funcional

[Cierta] Secuencia compatible con las dependencias actuales:

1. Ingresar como superadministrador.
2. Crear razón social activa y sedes con horarios operativos.
3. Crear profesionales desde **Profesionales**, con especialidad, matrícula, duración y agenda por sede; aceptar la invitación para acceder con esas cuentas.
4. Crear pacientes con correo único, cobertura y contactos necesarios. El alta genera cuenta e invitación.
5. Crear turnos para pacientes habilitados y profesionales activos dentro del horario de sede y agenda profesional.

[Cierta] El dashboard tiene datos fijos: comprobar las altas desde los listados de cada módulo. Usar exclusivamente datos ficticios en desarrollo y pruebas.
