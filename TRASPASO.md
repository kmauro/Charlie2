# Traspaso del proyecto Charlie2

[Cierta] Fecha de revisión: **24 de septiembre de 2026**. La evidencia técnica proviene del repositorio y de los comandos indicados en este documento. El build Docker, las migraciones y el login en contenedores fueron validados funcionalmente por la persona responsable del proyecto.

## Qué recibe la persona entrante

[Cierta] Un HIS administrativo con backend Laravel 13 y frontend Vue 3, TypeScript, Inertia 3 y Tailwind CSS 4. Referencias: [README](README.md), [arquitectura](ARQUITECTURA.md), [operación](OPERACION.md), [plan de producto](PLAN_PROYECTO.md) e [instrucciones del proyecto](AGENTS.md).

[Cierta] El sistema no implementa historias clínicas internas. Evoluciones, diagnósticos, recetas, medicación, estudios y adjuntos clínicos pertenecen a una webapp externa. No deben reintroducirse en este repositorio sin revisar expresamente esa decisión de alcance.

## Estado funcional

| Área | Implementado | Límite o pendiente |
| --- | --- | --- |
| Autenticación | Login/logout, validación de cuenta activa, invitaciones firmadas de uso único y recuperación de contraseña | Autenticación multifactor pendiente |
| Usuarios | Roles, permisos individuales efectivos, invitaciones, suspensión/restauración, protección del último superadministrador y alcance por múltiples sedes y razones sociales | No existen flujos de administración delegada fuera del superadministrador |
| Profesionales | Perfil, matrícula/especialidad, duración, agenda por sede, cuenta vinculada y estado | Descansos, feriados, licencias, excepciones y consultorios operativos pendientes |
| Pacientes | Identidad administrativa, cobertura/contactos, invitación, archivo/restauración, concurrencia y acceso de portal durante dos meses después del archivo | Menores, tutores, responsables de pago y autogestión completa pendientes |
| Organización | Razones sociales y sedes, validación, archivo/restauración y alcance de usuarios | No implica contabilidad separada implementada |
| Turnos | Altas, reprogramación, estados, cancelación, superposiciones, concurrencia y alcance organizacional | Sobreturnos, recurrencia, bloqueos, lista de espera y autogestión pendientes |
| Dashboard | Métricas operativas consultadas desde la base y contrato de prueba estabilizado | Su evolución dependerá de nuevos módulos operativos |
| Acceso clínico externo | Permiso por rol/usuario, relación asistencial temporal, alcance por sede para encargados, redirección HTTPS y auditoría | Sin SSO, token temporal ni contexto opaco del paciente; contrato productivo pendiente |
| Auditoría | Registro append-only por Eloquent, eventos estabilizados, filtros, interfaz, exportación exclusiva del auditor y retención anual con aviso por correo | Escrituras SQL directas quedan fuera de la protección del modelo; monitoreo avanzado pendiente |
| Guardia, internación y enfermería | Roles y definiciones de alcance | Circuitos operativos completos pendientes |
| Facturación, cobros, cajas, contabilidad y planes | Requisitos definidos en el plan | Módulos pendientes |
| Notificaciones | Invitaciones, recuperación de contraseña y aviso de retención de auditoría | Recordatorios de turnos, WhatsApp y seguimiento de entrega pendientes |

## Arquitectura vigente

[Cierta] El backend se organiza por dominio bajo `app/Domains`. Cada dominio puede contener controladores, modelos, requests, repositorios, servicios, policies, proveedores y demás componentes que le correspondan.

[Cierta] Factories, migraciones y seeders utilizan las carpetas estándar de Laravel:

```text
database/
  factories/
  migrations/
  seeders/
```

[Cierta] El frontend continúa organizado por dominio bajo `resources/js/domains` y comparte layouts, componentes y tipos desde `resources/js/shared`.

## Entorno Docker

[Cierta] La configuración está compuesta por:

| Servicio | Responsabilidad | Puerto local |
| --- | --- | --- |
| `app` | Apache, PHP y aplicación Laravel; ejecuta migraciones al iniciar | `8080` |
| `worker` | `php artisan queue:work` para correos y notificaciones | Sin puerto público |
| `scheduler` | `php artisan schedule:work` para tareas programadas | Sin puerto público |
| `database` | MySQL 8.4 con volumen persistente | `3307` |
| `mailpit` | SMTP de desarrollo e interfaz para inspeccionar correos | `8025` |

[Cierta] `app`, `worker` y `scheduler` se construyen localmente desde el mismo `Dockerfile`; no dependen de una imagen privada `charlie2-app`.

[Cierta] La imagen usa un build multietapa: Node 24 compila los recursos frontend y PHP 8.5 con Apache ejecuta la aplicación. Incluye las extensiones requeridas y valida su presencia durante el build.

[Cierta] La configuración Docker se controla mediante variables `DOCKER_*` incluidas en `.env.example`. El `.env` real permanece ignorado por Git. Mailpit es el destino SMTP predeterminado del entorno local, por lo que las credenciales SMTP personales no se incorporan a la imagen.

### Inicio

```powershell
docker compose up -d --build
docker compose ps
```

[Cierta] Aplicación: `http://localhost:8080`. Mailpit: `http://localhost:8025`.

### Operaciones frecuentes

```powershell
docker compose logs -f app
docker compose logs -f worker
docker compose logs -f scheduler
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
docker compose exec app php artisan db:seed --force
docker compose down
```

[Cierta] `docker compose down` conserva los volúmenes. El uso de `docker compose down -v` elimina la base y el almacenamiento persistente y debe reservarse para una reconstrucción deliberada.

### Validación Docker disponible

| Control | Estado |
| --- | --- |
| Build completo de las imágenes | Aprobado |
| Migraciones durante el arranque | Aprobado |
| Acceso mediante login | Aprobado |
| Procesamiento real del worker | Pendiente |
| Ejecución real del scheduler y limpieza de auditoría | Pendiente |
| Recepción de correos en Mailpit | Pendiente |
| Persistencia tras reiniciar o recrear contenedores | Pendiente |

## Verificación automatizada

| Comando | Resultado |
| --- | --- |
| `composer validate --strict` | Configuración Composer válida |
| `composer check-platform-reqs --no-dev` | Requisitos de producción aprobados |
| `php artisan route:list --except-vendor --json` | 59 rutas de aplicación |
| Conteo de `database/migrations` | 21 migraciones versionadas |
| `php artisan test --compact` | 173 pruebas aprobadas y 1728 aserciones |
| `node node_modules/vue-tsc/bin/vue-tsc.js --noEmit` | Chequeo TypeScript aprobado |
| `node node_modules/vite/bin/vite.js build` | Build frontend de producción aprobado |

[Cierta] La suite ya no contiene la discrepancia histórica del dashboard. Los permisos individuales se aplican en las policies y la vigencia de relación asistencial se limita temporalmente.

## Prioridades para continuar

| Orden | Tarea | Criterio de terminación propuesto |
| --- | --- | --- |
| 1 | Formalizar integración clínica externa | Proveedor, URL por entorno, autenticación, autorización, contexto, vencimiento, disponibilidad y soporte acordados |
| 2 | Implementar la integración segura acordada | Flujo extremo a extremo sin DNI, secretos ni identificadores sensibles en la URL |
| 3 | Completar aceptación Docker | Worker, scheduler, Mailpit y persistencia verificados con evidencia |
| 4 | Incorporar autenticación multifactor | Alta, recuperación y revocación cubiertas por pruebas |
| 5 | Completar agenda y turnos avanzados | Descansos, excepciones, consultorios, bloqueos, recurrencia y lista de espera priorizados con producto |
| 6 | Evolucionar módulos posteriores | Guardia, internación, enfermería, facturación, cajas, contabilidad, planes y notificaciones con criterios de aceptación previos |

## Información externa aún necesaria

- [ ] Proveedor clínico externo, contrato, URL por entorno y contacto de soporte.
- [ ] Mecanismo de SSO o token temporal y reglas para transmitir contexto opaco del paciente.
- [ ] Entornos productivos, dominios, hosting, acceso de infraestructura y procedimiento de despliegue.
- [ ] Secretos productivos entregados mediante un canal seguro.
- [ ] Proveedor de correo productivo y remitente autorizado.
- [ ] Política operativa de backups, ubicación, retención, responsables y evidencia de restauración.
- [ ] Responsable de privacidad, seguridad y validación normativa.
- [ ] Incidentes abiertos, compromisos con usuarios y prioridades funcionales vigentes.

## Recorrido de aceptación restante

1. Crear un usuario invitado y comprobar el correo en Mailpit mediante el worker.
2. Ejecutar manualmente el scheduler y verificar el comando de retención en modo `--dry-run`.
3. Reiniciar y recrear los servicios sin eliminar volúmenes; confirmar que base, sesiones y almacenamiento persisten.
4. Crear razón social, múltiples sedes y un usuario con alcance limitado; comprobar listados y operaciones permitidas.
5. Crear profesional, paciente y turno; verificar acceso clínico permitido y denegado dentro y fuera de la ventana temporal.
6. Comprobar consulta y exportación de auditoría con auditor, y rechazo con otros roles.
7. Documentar los resultados y resolver cualquier diferencia antes de considerar cerrado el entorno reproducible.

[Cierta] La rama local contiene commits de estabilización y reorganización que todavía requieren el `push` indicado por la persona responsable después del commit de esta entrega.
