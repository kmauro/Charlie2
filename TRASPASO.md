# Traspaso del proyecto Charlie2

[Cierta] Fecha de revisión: **8 de octubre de 2026**. La evidencia técnica proviene del repositorio y de los comandos indicados en este documento. El build Docker, las migraciones y el login en contenedores fueron validados funcionalmente por la persona responsable del proyecto.

## Qué recibe la persona entrante

[Cierta] Un HIS administrativo con backend Laravel 13 y frontend Vue 3, TypeScript, Inertia 3 y Tailwind CSS 4. Referencias: [README](README.md), [arquitectura](ARQUITECTURA.md), [operación](OPERACION.md), [plan de producto](PLAN_PROYECTO.md) e [instrucciones del proyecto](AGENTS.md).

[Cierta] El sistema no implementa historias clínicas internas. Evoluciones, diagnósticos, recetas, medicación, estudios y adjuntos clínicos pertenecen a una webapp externa. No deben reintroducirse en este repositorio sin revisar expresamente esa decisión de alcance.

## Estado funcional

| Área | Implementado | Límite o pendiente |
| --- | --- | --- |
| Autenticación | Login/logout, validación de cuenta activa, invitaciones firmadas de uso único y recuperación de contraseña | Autenticación multifactor pendiente |
| Usuarios | Roles, permisos individuales efectivos, invitaciones, suspensión/restauración, protección del último superadministrador y alcance por múltiples sedes y razones sociales | No existen flujos de administración delegada fuera del superadministrador |
| Profesionales | Perfil, matrícula/especialidad, duración, agenda por sede, cuenta vinculada y agenda operativa con descansos, feriados, licencias, excepciones y consultorios | Los criterios operativos finales quedan sujetos a la configuración de cada institución |
| Pacientes | Identidad administrativa, cobertura/contactos, invitación, archivo/restauración, concurrencia, portal, autogestión, menores, tutores, representantes y responsables de pago | El contrato de integración con la webapp clínica externa continúa pendiente |
| Organización | Razones sociales y sedes, validación, archivo/restauración y alcance de usuarios | No implica contabilidad separada implementada |
| Turnos | Altas, reprogramación, estados, cancelación, superposiciones, concurrencia, alcance organizacional, recurrencia, bloqueos, lista de espera, sobreturnos, autogestión y tipos virtuales, de guardia y procedimientos | La evolución posterior dependerá de las reglas operativas de cada institución |
| Dashboard | Métricas operativas consultadas desde la base y contrato de prueba estabilizado | Su evolución dependerá de nuevos módulos operativos |
| Acceso clínico externo | Permiso por rol/usuario, relación asistencial temporal, alcance por sede para encargados, redirección HTTPS y auditoría | Sin SSO, token temporal ni contexto opaco del paciente; contrato productivo pendiente |
| Auditoría | Registro append-only por Eloquent, eventos estabilizados, filtros, interfaz, exportación exclusiva del auditor y retención anual con aviso por correo | Escrituras SQL directas quedan fuera de la protección del modelo; monitoreo avanzado pendiente |
| Guardia, internación y enfermería | Roles y definiciones de alcance | Circuitos operativos completos pendientes |
| Facturación, cobros, cajas y contabilidad | MVP operativo: facturas, anticipos, cobros parciales/totales, caja por sede, cierres, notas de crédito, devoluciones con autorización separada y asientos automáticos | Sin emisión electrónica ARCA, conciliación bancaria, administración de medios de pago ni reportes contables de cierre |
| Planes de prestaciones | Planes, contratos históricos, vigencias, renovaciones y cobros recurrentes integrados con facturación y responsables de pago | La evolución posterior dependerá de las reglas comerciales de cada institución |
| Notificaciones | Invitaciones, recuperación de contraseña, aviso de retención de auditoría, recordatorios de turnos, preferencias, seguimiento de entrega y proveedor desacoplado para WhatsApp | La operación depende de la configuración de proveedores por entorno |

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
| Procesamiento real del worker | Aprobado |
| Ejecución real del scheduler y limpieza de auditoría | Aprobado |
| Recepción de correos en Mailpit | Aprobado |
| Persistencia tras reiniciar o recrear contenedores | Aprobado |

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
| 1 | Consolidar el MVP de facturación | Emisión, cobro, caja, cierre, nota de crédito, devolución y asiento probados de extremo a extremo; permisos y saldos consistentes |
| 2 | Evolucionar facturación y contabilidad | Reglas fiscales definitivas, ARCA, conciliación bancaria, administración de medios de pago e informes de período definidos e implementados |
| 3 | Implementar guardia, internación y enfermería operativas | Circuitos administrativos no clínicos, autorización y trazabilidad definidos y cubiertos por pruebas |
| 4 | Completar operación y seguridad avanzada | Monitoreo, alertas, backups con restauración probada, TLS, cifrado e interoperabilidad con responsables y evidencia operativa |
| 5 | Formalizar e implementar la integración clínica externa | Contrato, URL por entorno, autenticación, autorización y contexto opaco acordados y verificados sin exponer datos sensibles |
| 6 | Incorporar autenticación multifactor | Alta, recuperación, revocación y pruebas de los factores definidos |

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
