# Arquitectura y reglas funcionales

[Cierta] Estado leído del código el 7 de septiembre de 2026. Inicio: [README](README.md). Los requisitos futuros están en [PLAN_PROYECTO.md](PLAN_PROYECTO.md).

## Organización del código

[Cierta] Aplicación Laravel con Vue e Inertia. Las rutas principales están en [routes/web.php](routes/web.php); `bootstrap/app.php` no registra una API de negocio separada.

```text
app/Domains/
  Appointments/             Turnos
  Audits/                   Auditoría
  Auth/                     Sesiones y aceptación de invitaciones
  Dashboard/                Datos fijos del tablero
  ExternalClinicalRecords/  Autorización y redirección externa
  Organizations/            Razones sociales y sedes
  Patients/                 Fichas administrativas y contactos
  Professionals/            Profesionales y agendas
  Users/                    Usuarios, roles e invitaciones
resources/js/
  domains/                  Páginas, formularios y tipos por dominio
  shared/                   Componentes, layouts, composables y tipos
database/migrations/        Tablas de infraestructura
app/Domains/*/Database/     Migraciones y, donde existen, factories y seeders
```

[Cierta] Patrón predominante: ruta → controlador/Form Request/policy → servicio → contrato de repositorio → Eloquent. No todos los dominios requieren todas las capas; algunos servicios consultan modelos directamente. Mantener las convenciones de archivos vecinos.

[Cierta] [bootstrap/providers.php](bootstrap/providers.php) registra los proveedores, que enlazan repositorios, registran policies y cargan migraciones con `loadMigrationsFrom`. `UsersServiceProvider` está directamente en `app/Domains/Users`; los demás proveedores de dominio usan `Providers/`.

[Cierta] Las páginas viven en `resources/js/domains`, no en `resources/js/Pages`. [app.ts](resources/js/app.ts) configura esa resolución; ejemplo: `Inertia::render('patients/views/Show', ...)`. [vite.config.ts](vite.config.ts) declara las entradas CSS/TypeScript y desactiva SSR.

[Cierta] [HandleInertiaRequests](app/Http/Middleware/HandleInertiaRequests.php) comparte nombre de aplicación, identidad/rol e `interfaceMode=true`. Los controladores entregan permisos específicos de cada pantalla. Layout común: [AppShell.vue](resources/js/shared/layouts/AppShell.vue); tema: [useTheme.ts](resources/js/shared/composables/useTheme.ts).

## Datos y relaciones

[Cierta] Resumen de modelos y migraciones, sin inspección de datos productivos:

| Tabla | Relaciones y restricciones relevantes |
| --- | --- |
| `roles` | Código único; catálogo sembrado desde `RoleCode`. |
| `users` | Rol, estado, correo único, permisos JSON, contraseña hasheada y metadatos de invitación. |
| `professionals` | Cuenta `user_id` única, documento, matrícula, especialidad, duración y modalidad de consultorio. Una migración posterior restringe borrar su usuario. |
| `professional_schedules` | Horarios del profesional y vínculo a sede; migraciones posteriores agregan y completan `location_id`. |
| `legal_entities` | Razón social y CUIT único. |
| `locations` | Razón social, código único, horarios y configuración operativa. |
| `patients` | Documento único, identidad, cobertura, estado, `lock_version`, `previous_status`, `archived_at`. Globales al grupo, sin pertenencia a sede. |
| `patients.user_id` | Nullable y único; borrado restringido. Las altas actuales crean una cuenta; registros previos pueden no tenerla. |
| `patient_contacts` | Contactos y flags de emergencia, representación legal y responsabilidad financiera. |
| `appointments` | Paciente, profesional y sede con borrado restringido; fechas, duración, tipo, estado, consultorio, cancelación y `lock_version`. |
| `audit_logs` | Actor y recurso polimórfico; evento, resultado, correlación, IP, agente y snapshots. |
| Infraestructura | `sessions`, `password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`. Una tabla no implica que exista un flujo implementado. |

## Autorización efectiva

[Cierta] Las rutas internas exigen sesión y [EnsureUserIsActive](app/Domains/Auth/Middleware/EnsureUserIsActive.php). Las policies bajo `app/Domains/*/Policies/` determinan estos accesos; los servicios añaden restricciones de negocio:

| Área | Lectura | Operaciones |
| --- | --- | --- |
| Usuarios | Superadministrador | Superadministrador; protección contra autosuspensión y pérdida del último superadministrador activo. |
| Profesionales | Superadministrador | Superadministrador; cuenta y estado se gestionan desde este módulo. |
| Razones sociales / sedes | Superadministrador, administrador, gerente, encargado | Superadministrador y administrador. |
| Pacientes | Superadministrador, administrador, gerente, encargado, recepción, profesional, enfermería, guardia | Alta/edición: primeros cinco. Archivo/restauración/cambio de estado: primeros cuatro. |
| Turnos | Los mismos ocho roles; profesional limitado a sus turnos | Superadministrador, administrador, gerente, encargado y recepción. |
| Historia clínica externa | Profesional con la relación descrita más abajo | Redirección; no hay excepción automática para superadministrador. |
| Dashboard | Usuario autenticado y activo | Datos fijos. |

[Cierta] Contabilidad, ventas, auditor y paciente existen como roles, pero no tienen acceso por estas policies a los módulos anteriores, salvo dashboard. Una cuenta de paciente no implica un portal de autogestión.

[Cierta] `UserPermission` enumera `manage_appointments`, `record_payments`, `access_external_clinical_record`, `view_accounting` y `export_audit`. Se validan y guardan en `users.permissions`, pero **las policies actuales no consultan ese array**. Seleccionarlos no amplía los accesos. Los permisos enviados por los controladores al frontend son resultados de autorización, no ese catálogo individual.

## Reglas y flujos

### Invitaciones

[Cierta] [UserService](app/Domains/Users/Services/UserService.php) crea usuario pendiente, token aleatorio cuyo hash SHA-256 se almacena y URL firmada con vencimiento; plazo predeterminado: 48 horas. La aceptación activa la cuenta y consume la invitación. Reinvitación: solo para cuentas no activadas y no suspendidas. No existe alta pública espontánea.

[Cierta] Las invitaciones usan `Mail::send`, de forma síncrona. El worker de colas no controla ese envío. Las altas de pacientes y profesionales lo invocan dentro de transacciones; un cambio a envío diferido debe contemplar commit, reintentos y consistencia entre cuenta y perfil.

[Cierta] [LoginRequest](app/Domains/Auth/Requests/LoginRequest.php) limita intentos por correo/IP y exige estado activo y rol. No hay rutas de recuperación de contraseña, aunque exista la tabla estándar de tokens.

### Pacientes

[Cierta] [StorePatientRequest](app/Domains/Patients/Requests/StorePatientRequest.php) valida DNI normalizado de 7 a 9 dígitos, nacimiento no futuro y correo obligatorio único respecto de pacientes y usuarios. Cobertura obligatoria; número de afiliado requerido salvo `Particular`. Los menores necesitan representante legal entre sus contactos.

[Cierta] [PatientService](app/Domains/Patients/Services/PatientService.php) crea cuenta con rol paciente e invitación; al editar sincroniza nombre/correo con la cuenta vinculada. Usa bloqueo transaccional y `lock_version`; una versión obsoleta devuelve error de validación y exige recargar.

[Cierta] El archivo es lógico y conserva el estado anterior. Los archivados deben restaurarse antes de editar. El modelo impide borrado físico mediante su flujo Eloquent. Archivar la ficha no invoca suspensión de la cuenta de usuario.

### Organización y profesionales

[Cierta] [OrganizationService](app/Domains/Organizations/Services/OrganizationService.php) impide archivar razones sociales con sedes operativas e inactivarlas con sedes activas. La razón social seleccionada para una sede debe estar activa; los registros archivados deben restaurarse antes de editar.

[Cierta] [ProfessionalService](app/Domains/Professionals/Services/ProfessionalService.php) coordina perfil, agenda e invitación. No crear profesionales como usuarios genéricos: el servicio bloquea esa vía y cambios incompatibles de rol. Suspensión/restauración de cuentas profesionales se administra desde su dominio.

### Turnos

[Cierta] [AppointmentService](app/Domains/Appointments/Services/AppointmentService.php) aplica:

- Paciente activo o internado, profesional activo y sede activa.
- Fecha futura, duración según profesional, horario de sede y agenda profesional.
- Control de superposición de profesional, paciente y consultorio.
- Bloqueo transaccional y `lock_version` para modificaciones.
- Reprogramación de reservados/confirmados.
- Cancelación de reservados/confirmados con al menos 24 horas de anticipación; motivo, actor y fecha registrados.
- Tipos presencial, virtual, guardia y procedimiento; virtual no asigna consultorio. El tipo guardia no constituye un módulo completo de guardia.

[Cierta] Transiciones de [AppointmentStatus](app/Domains/Appointments/Enums/AppointmentStatus.php):

| Estado | Destinos por cambio de estado |
| --- | --- |
| Reservado | Confirmado, en espera, presente, atendido, ausente |
| Confirmado | En espera, presente, atendido, ausente |
| En espera | Presente, atendido, ausente |
| Presente | Atendido, ausente |
| Atendido, cancelado, ausente | Ninguno |

[Cierta] Cancelar usa una acción separada. Reservado, confirmado, en espera y presente bloquean disponibilidad. `is_overbook` se guarda en falso en las altas actuales. Sobreturnos, recurrencia y lista de espera para obtener turno siguen pendientes; esta última no equivale al estado «en espera».

### Historia clínica externa

[Cierta] Ruta `patients.external_clinical_record`: [controlador](app/Domains/ExternalClinicalRecords/Controllers/ExternalClinicalRecordController.php), [autorización](app/Domains/ExternalClinicalRecords/Services/ExternalClinicalAccessService.php) y [destino](app/Domains/ExternalClinicalRecords/Services/ExternalClinicalRecordLinkService.php).

[Cierta] Exige rol profesional y al menos un turno del paciente con un profesional activo asociado al usuario, excluyendo cancelados y ausentes. **No filtra fechas**: turnos antiguos o atendidos pueden habilitar el acceso. Esta es la implementación actual del texto «relación asistencial vigente».

[Cierta] Valida URL y HTTPS. Acceso denegado: 403. Destino inválido: 503. Acceso autorizado: redirección a la URL configurada sin agregar identificador de paciente, token ni SSO. La webapp externa debe autenticar y autorizar por su cuenta; no se comprueba su disponibilidad.

[Cierta] Eventos auditados: `external_clinical_record.access_denied`, `external_clinical_record.configuration_failed`, `external_clinical_record.opened`. «Opened» acredita autorización de redirección, no apertura exitosa en el proveedor.

### Auditoría

[Cierta] [AuditService](app/Domains/Audits/Services/AuditService.php) registra snapshots explícitos y contexto. Está integrado en pacientes, organización, turnos y acceso clínico externo, no en toda la aplicación.

[Cierta] [AuditLog](app/Domains/Audits/Models/AuditLog.php) cifra `before_values`, `after_values` y `reason`; bloquea actualizar/eliminar instancias Eloquent. Esto no protege frente a SQL directo o consultas masivas que omitan el modelo. Los snapshots contienen información personal administrativa; su recuperación requiere conservar `APP_KEY`.

## Continuidad del desarrollo

[Cierta] [AGENTS.md](AGENTS.md) exige respetar dominios, generar clases con Artisan, usar validación/autorización y consultar documentación versionada con Boost antes de cambios de código. Las habilidades Laravel, Pest y Tailwind están en `.agents/skills/`. El modelo de autenticación es `App\Domains\Users\Models\User`, no `App\Models\User`.

[Suposición] Procedimiento propuesto para cada cambio:

1. Confirmar requisito y permisos frente al plan y a las policies reales.
2. Revisar archivos vecinos del dominio y reutilizar componentes de `resources/js/shared`.
3. Agregar migraciones si cambia el esquema; conservar carga desde el proveedor y no reescribir migraciones aplicadas.
4. Mantener coherentes props Inertia, tipos TypeScript y formularios Vue.
5. Verificar reglas y accesos con pruebas y factories ficticias; ejecutar pruebas pertinentes, typecheck, build y Pint para PHP.
6. Actualizar los documentos cuando cambie el comportamiento.
