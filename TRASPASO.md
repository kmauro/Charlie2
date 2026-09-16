# Traspaso del proyecto Charlie2

[Cierta] Fecha de revisión: **7 de septiembre de 2026**. Evidencia: archivos de esta copia y comandos indicados más abajo. Esta revisión no acredita operación en producción, estado de una base MySQL desplegada ni conexión real con proveedores.

## Qué recibe la persona entrante

[Cierta] Un HIS administrativo con backend Laravel y frontend Vue/Inertia. Guía de entrada: [README](README.md). Referencias: [arquitectura](ARQUITECTURA.md), [operación](OPERACION.md), [plan de producto](PLAN_PROYECTO.md) e [instrucciones del proyecto](AGENTS.md).

[Cierta] El sistema no implementa historias clínicas internas. Evoluciones, diagnósticos, recetas, medicación, estudios y adjuntos clínicos pertenecen al sistema externo según el alcance definido. No volver a introducirlos como parte de una continuación administrativa sin revisar esa decisión.

## Estado funcional

[Cierta] Estado contrastado con rutas, servicios, policies y frontend:

| Área | Implementado | Límite o pendiente |
| --- | --- | --- |
| Autenticación | Login/logout, validación de estado, registro por invitación firmada y de uso único | Recuperación de contraseña sin flujo/rutas implementados. |
| Usuarios | Roles, invitaciones, reinvitación, suspensión/restauración y protección del último superadministrador | Permisos individuales persistidos, sin aplicación en las policies actuales. |
| Profesionales | Perfil, matrícula/especialidad, duración, agenda por sede, cuenta vinculada y estado | Auditoría del dominio pendiente. |
| Pacientes | Identidad administrativa, cobertura/contactos, invitación de cuenta, archivo/restauración y concurrencia | Sin autogestión de pacientes; revisar alcance de las cuentas creadas. |
| Organización | Razones sociales y sedes, validación, archivo/restauración | No implica contabilidad separada implementada. |
| Turnos | Altas, reprogramación, estados, cancelación, superposiciones y concurrencia | Sobreturnos, recurrencia, bloqueos, lista de espera y autogestión pendientes. |
| Dashboard | Interfaz y props con datos fijos | No consulta métricas reales; una prueba falla. |
| Acceso clínico externo | Autorización local, redirección HTTPS y auditoría | Sin SSO, token temporal ni contexto de paciente; contrato productivo pendiente. |
| Auditoría | Pacientes, organización, turnos y acceso externo; snapshots cifrados | Cobertura parcial, sin interfaz avanzada de consulta/exportación. |
| Guardia, internación y enfermería | Roles/estados/tipos relacionados en el núcleo | Circuitos operativos completos pendientes. |
| Facturación, cobros, cajas, contabilidad, planes | Requisitos en el plan | Módulos pendientes. |
| Notificaciones | Correo de invitación | Recordatorios y canales administrativos externos pendientes. |

## Verificación realizada

[Cierta] Resultados de esta sesión:

| Comando | Resultado |
| --- | --- |
| `php -v` | PHP 8.5.1. |
| `composer --version` | Composer 2.9.5. |
| `node -v` | Node 24.19.0. |
| `composer check-platform-reqs` | Requisitos de los paquetes instalados aprobados. |
| `php artisan route:list --except-vendor --no-interaction` | 53 rutas en ese listado. |
| `php artisan test --compact` | 148 pruebas: 147 aprobadas, una fallida; 1516 aserciones reportadas. |
| `node node_modules/vue-tsc/bin/vue-tsc.js --noEmit` | Aprobado; equivale al ejecutable del script `npm run typecheck`. |
| `node node_modules/vite/bin/vite.js build` | Aprobado; Vite 8.1.5 generó `public/build`. Equivale al ejecutable de `npm run build`. |
| `git status --short` | Esta carpeta no contiene un repositorio Git reconocible. |

[Cierta] npm no estaba disponible como comando en la terminal de revisión; se usaron Node y las dependencias ya instaladas para verificar frontend. No se verificó una instalación limpia con `npm ci`/`composer install`, no se migró ni sembró la base de trabajo y no se realizó prueba manual de navegador, entrega SMTP o integración externa real.

[Cierta] Fallo reproducido: [DashboardTest.php](tests/Feature/DashboardTest.php) espera `metrics.0.value = '128'`; [DashboardController.php](app/Domains/Dashboard/Controllers/DashboardController.php) devuelve `'0'`. La prueba también espera `82% confirmados` y el controlador define `0% confirmados`. Reproducción:

```powershell
php artisan test --compact --filter=DashboardTest
```

[Cierta] El plan del 24 de agosto ya registraba este problema. El resultado actual no debe presentarse como una suite completamente aprobada. Typecheck/build no sustituyen validación visual ni pruebas de concurrencia en MySQL.

## Prioridades para continuar

[Suposición] Orden propuesto a partir de las diferencias entre plan e implementación; requiere priorización de la persona responsable de producto:

| Orden | Tarea | Criterio de terminación propuesto |
| --- | --- | --- |
| 1 | Definir contrato del dashboard y resolver el fallo | UI y prueba coherentes; decidir explícitamente métricas reales o demostración y lograr la suite aprobada. |
| 2 | Formalizar integración clínica externa | Proveedor, URL, autenticación, autorización, contexto, disponibilidad y soporte acordados; prueba de extremo a extremo sin información sensible en URL. |
| 3 | Definir vigencia de relación asistencial | Establecer límite temporal y estados admitidos; cubrir turnos antiguos, futuros, atendidos y perfiles inactivos. |
| 4 | Resolver permisos individuales | Decidir cómo se combinan con roles y completar policies/pruebas; evitar opciones que prometan acceso sin efecto. |
| 5 | Completar auditoría | Autenticación, usuarios, permisos y profesionales con eventos definidos; acceso/retención y protección frente a escritura directa acordados. |
| 6 | Preparar operación reproducible | Obtener repositorio/historial, validar instalación limpia, documentar entorno real, despliegue, backup y ensayo de restauración. |
| 7 | Aclarar ciclo de vida de pacientes y cuentas | Definir autogestión, cuentas de registros previos, recuperación de acceso y relación entre archivo de ficha y suspensión de cuenta. |
| 8 | Evolucionar turnos y módulos futuros | Priorizar con producto desde `PLAN_PROYECTO.md` y agregar criterios de aceptación antes de implementar. |

[Cierta] Hallazgos técnicos que explican estas prioridades:

- `ExternalClinicalAccessService` no filtra fechas; un turno antiguo puede satisfacer la «relación vigente».
- `UserPermission` se persiste, pero las policies se basan en roles y relación asistencial.
- `AuditLog` protege instancias Eloquent, no toda escritura SQL posible.
- `PatientService::create` crea cuenta e invitación; archivar ficha no suspende esa cuenta.
- Las invitaciones se envían sincrónicamente, incluso desde transacciones de altas relacionadas.
- La copia recibida no permite identificar rama, commit, remoto ni cambios locales mediante Git.

## Información que debe entregar la persona saliente

[Suposición] Lista pendiente de completar con responsables reales. Ninguno de los siguientes accesos o acuerdos se deduce de la copia revisada:

- [ ] Repositorio oficial, historial, rama de trabajo, versión desplegada y cambios sin publicar.
- [ ] Responsable funcional que decide prioridades y persona de contacto técnico.
- [ ] Entornos existentes, dominios, hosting, accesos de servidor/base y procedimiento de despliegue.
- [ ] Configuración privada y claves en un canal seguro, incluida `APP_KEY` de cada base existente.
- [ ] Acceso administrativo para la persona entrante y plan de retiro del acceso saliente.
- [ ] Proveedor de correo, remitente autorizado y destinatario de prueba.
- [ ] Proveedor clínico externo, contrato, URL por entorno y contacto de soporte.
- [ ] Backups, retención, ubicación, responsables y evidencia de restauración.
- [ ] Incidentes abiertos, compromisos con usuarios y prioridades ya acordadas.
- [ ] Responsable de las decisiones sobre privacidad, acceso y conservación de datos.

## Recorrido de aceptación del traspaso

[Suposición] La persona entrante debería completar en una instalación aislada con datos ficticios:

1. Seguir el README y acceder como superadministrador.
2. Crear razón social, sede y agenda profesional; aceptar invitación de prueba.
3. Crear paciente, comprobar cuenta/invitación y modificar su ficha.
4. Crear turno; comprobar superposición, estados, cancelación y rechazo de versión obsoleta.
5. Verificar diferencias de acceso entre superadministrador, recepción y profesional.
6. Revisar acceso externo permitido/denegado y auditoría sin asumir que la redirección implementa SSO.
7. Ejecutar las verificaciones y registrar el tratamiento acordado del fallo del dashboard.
8. Explicar cómo desplegar, recuperar un backup y contactar a responsables; completar los casilleros anteriores.

[Cierta] Esta entrega modifica documentación; no corrige los defectos descritos ni habilita funcionalidades pendientes. La verificación frontend regeneró los artefactos locales de `public/build`.
