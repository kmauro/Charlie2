# Plan del proyecto HIS

> [Cierta] Estado actualizado al 24 de septiembre de 2026. Para el detalle de verificaciones, límites operativos y continuidad consultar [TRASPASO.md](TRASPASO.md). La instalación está en [README.md](README.md), las reglas implementadas en [ARQUITECTURA.md](ARQUITECTURA.md) y el mantenimiento en [OPERACION.md](OPERACION.md).

## Estado del documento

- Estado: núcleo administrativo consolidado e integración clínica externa básica implementada.
- Fecha de actualización: 24 de septiembre de 2026.
- Fase actual: estabilización de los módulos implementados y definición del contrato definitivo con la webapp clínica externa.
- Registro: exclusivamente mediante invitación del superadministrador.
- Acceso clínico: la información clínica se administrará exclusivamente en una webapp externa; este sistema sólo ofrecerá un enlace autorizado hacia ella.
- Estado técnico verificado:
  - 59 rutas de aplicación y 21 migraciones versionadas.
  - Frontend TypeScript y build de producción aprobados.
  - 173 pruebas automatizadas y 1728 aserciones aprobadas.
  - Dashboard conectado a métricas operativas y contrato de prueba estabilizado.
  - Permisos individuales, alcance por múltiples sedes y razones sociales, vigencia asistencial, recuperación de contraseña y ciclo de acceso del paciente estabilizados.
  - Auditoría con consulta, exportación, retención anual y aviso previo por correo implementada.
  - Imagen Docker construida correctamente; migraciones y acceso por login verificados en contenedores según validación funcional del 24 de septiembre de 2026.
  - Quedan pendientes las pruebas integrales de cola, scheduler, entrega en Mailpit y persistencia tras reinicios.
  - El esquema no contiene tablas, adjuntos ni alertas de historia clínica interna.

## 1. Objetivo

Construir un sistema de información hospitalaria (HIS) para una clínica privada con varias sedes, potencialmente pertenecientes a razones sociales distintas, pero administradas por el mismo grupo.

El sistema abarcará progresivamente:

- Gestión administrativa de pacientes y acceso autorizado a la webapp clínica externa.
- Gestión de profesionales, especialidades, agendas y consultorios.
- Gestión de sedes y razones sociales.
- Gestión de usuarios, roles y permisos.
- Turnos.
- Guardia, internación y enfermería en sus aspectos operativos no clínicos.
- Facturación, cobros, cajas y contabilidad.
- Planes de prestaciones.
- Notificaciones.
- Auditoría integral y protección de datos sensibles.

Este sistema no almacenará ni administrará historias clínicas, evoluciones, diagnósticos, estudios, recetas, indicaciones, medicación, alertas clínicas ni adjuntos clínicos. Esas funciones pertenecerán a una webapp externa.

## 2. Alcance inicial y avance

La primera fase contempló:

1. Autenticación:
   - Inicio de sesión.
   - Cierre de sesión.
   - Registro por invitación.
2. Dashboard.
3. Usuarios.
4. Profesionales.
5. Pacientes.
6. Razones sociales y sedes.

### Avance verificado

- Autenticación: implementada, incluido el registro mediante invitaciones firmadas, temporales y de un solo uso.
- Dashboard: interfaz implementada con métricas operativas y pruebas coherentes.
- Usuarios: gestión, roles, permisos individuales efectivos, suspensión, restauración, reinvitación, recuperación de contraseña y alcance por múltiples sedes y razones sociales implementados.
- Profesionales: gestión, matrícula, especialidad, duración de turnos, horarios por sede, descansos, feriados, licencias, excepciones por fecha y agenda operativa implementados.
- Pacientes: gestión administrativa, contactos, cobertura, estados, archivado y control de concurrencia implementados.
- Razones sociales y sedes: persistencia, validación, archivado y restauración implementados.
- Turnos: núcleo funcional implementado con asignación manual o automática de consultorio y bloqueo de conflictos de profesional, paciente, agenda y consultorio.
- Auditoría: base append-only, eventos de autenticación y dominios estabilizados, consulta, filtros, exportación exclusiva del auditor y retención anual con aviso por correo implementados.
- Historia clínica interna: retirada del código, las rutas, la interfaz, las migraciones, las pruebas y la base de datos.
- Acceso clínico externo: implementado desde la ficha del paciente para profesionales con relación asistencial vigente, mediante una URL HTTPS configurable y con auditoría de accesos permitidos, denegados y fallos de configuración.

El menú sólo mostrará módulos implementados y vigentes. La historia clínica interna no deberá mostrarse; la ficha del paciente ofrecerá únicamente el acceso autorizado a la webapp externa.

La interfaz puede incluir formularios, tarjetas y datos sintéticos mínimos para comunicar la estructura visual. No se usarán datos reales de pacientes ni información clínica en desarrollo, pruebas, demostraciones, analítica ni herramientas de inteligencia artificial.

## 3. Tecnología

- Backend: PHP 8.5 y Laravel 13.
- Base de datos: MySQL.
- Frontend: Vue 3 con TypeScript.
- Estilos: Tailwind CSS 4.
- Integración entre Laravel y Vue: Inertia 3, implementada.
- Pruebas: Pest 4.
- Idioma: español de Argentina.
- Moneda inicial: peso argentino.
- Zona horaria: America/Argentina/Buenos_Aires.
- Formatos locales:
  - Fechas: DD/MM/AAAA.
  - Documento: DNI.
  - Identificación fiscal: CUIT.

### Contenedores Docker

- `Dockerfile` multietapa con Node 24 para los recursos frontend y PHP 8.5 con Apache para la aplicación.
- Una misma definición de build local se reutiliza para los servicios `app`, `worker` y `scheduler`.
- `app` publica el puerto 8080, espera a MySQL y ejecuta las migraciones automáticamente.
- `worker` procesa las notificaciones e invitaciones en cola mediante la conexión de base de datos.
- `scheduler` mantiene activo `php artisan schedule:work`, incluida la limpieza anual de auditoría.
- MySQL 8.4 conserva los datos en un volumen y se publica localmente en el puerto 3307.
- Mailpit recibe el correo de desarrollo por SMTP en el puerto interno 1025 y expone su interfaz en el puerto 8025.
- El almacenamiento de Laravel se comparte mediante un volumen persistente entre los procesos de aplicación.
- Si `APP_KEY` no está definida, el entrypoint genera y conserva una clave compartida en el volumen de almacenamiento.
- La imagen activa `php artisan optimize` cuando `DOCKER_APP_ENV=production`.
- Las variables `DOCKER_*` están documentadas en `.env.example`; las credenciales productivas deberán inyectarse mediante un canal seguro.

## 4. Organización, razones sociales y sedes

### Estructura organizativa

- Un grupo administrador puede manejar varias razones sociales.
- Cada razón social puede tener una o más sedes.
- Cada sede tendrá sus propios datos operativos, consultorios, agendas, cajas e internaciones.
- Los pacientes son globales al grupo y no pertenecen a una sede específica.
- Los profesionales pueden atender en varias sedes.
- Cada profesional tendrá inicialmente una especialidad y una matrícula.

### Separación contable y legal

- La facturación, numeración de comprobantes, cajas y cierres deberán quedar asociados a la razón social y sede correspondientes.
- Los usuarios podrán recibir alcance sobre una o más sedes mediante permisos individuales.
- La información clínica longitudinal residirá exclusivamente en la webapp externa y no se duplicará en este sistema ni por sede.

## 5. Usuarios, roles y permisos

### Roles iniciales

- Superadministrador.
- Administrador.
- Gerente.
- Encargado.
- Recepción.
- Profesional.
- Enfermería.
- Guardia.
- Contabilidad.
- Ventas.
- Auditor.
- Paciente.

### Reglas

- Cada usuario tendrá un único rol.
- Los permisos podrán configurarse individualmente.
- Sólo el superadministrador podrá crear, suspender, recuperar y administrar usuarios.
- El portal del paciente tendrá navegación y presentación separadas del panel interno.
- Recepción:
  - Puede consultar y asignar turnos.
  - Puede cobrar.
  - No puede acceder a la webapp clínica externa.
  - No puede acceder a contabilidad.
- Ventas:
  - Registra ventas.
  - Corrobora pagos.
  - Registra movimientos de caja chica.
- Contabilidad:
  - Realiza cierres de caja chica.
  - Gestiona el balance mensual.
- Auditor:
  - Es el único rol que puede consultar y exportar el registro de auditoría.
- Enfermería:
  - Puede localizar a todos los pacientes.
  - Su acceso a la webapp externa se regirá por permisos, contexto operativo y las reglas de la plataforma clínica.
- Profesional:
  - Puede localizar a todos los pacientes.
  - Sólo puede abrir el enlace a la webapp clínica cuando existe una relación asistencial vigente.
  - La webapp externa realizará la autorización clínica definitiva.

## 6. Pacientes

### Datos

- DNI.
- Nombres.
- Apellidos.
- Fecha de nacimiento.
- Sexo registral.
- Género.
- Teléfono.
- Correo electrónico.
- Domicilio.
- Cobertura.
- Número de afiliado.
- Contacto de emergencia.
- Referencia técnica mínima para vincular al paciente con la webapp externa, únicamente si la integración la requiere.

### Reglas

- Se contemplarán menores, tutores, representantes y responsables de pago.
- Cada paciente tendrá una sola cobertura activa.
- Estados:
  - Activo.
  - Internado.
  - Archivado.
  - Fallecido.
  - Bloqueado.
- Un paciente no se elimina físicamente: se cambia a estado archivado.
- La ficha administrativa no almacenará alertas, diagnósticos, evoluciones ni otros datos clínicos.

## 7. Integración con la webapp clínica externa

### Límite funcional

- La historia clínica y toda la información clínica se gestionarán exclusivamente en una webapp externa.
- Este sistema no persistirá, editará, visualizará, indexará ni replicará contenido clínico.
- No se implementarán internamente evoluciones, diagnósticos, antecedentes, estudios, recetas, indicaciones, medicación, alertas clínicas ni adjuntos clínicos.
- La ficha del paciente mostrará un enlace o acción para abrir la webapp externa cuando el usuario esté autorizado.
- La integración deberá evitar datos clínicos y datos personales sensibles en la URL.

### Autorización e integración

- El enlace sólo se ofrecerá a roles habilitados y, para profesionales, requerirá una relación asistencial vigente.
- La webapp externa será responsable de autenticar y autorizar el acceso final.
- La primera integración abre una URL HTTPS configurada sin incluir el DNI ni el identificador del paciente. El inicio de sesión federado o un mecanismo temporal firmado deberá definirse con el proveedor antes de habilitar el enlace en producción.
- Los identificadores intercambiados deberán ser opacos, mínimos, temporales cuando sea posible y protegidos contra manipulación y reutilización.
- La URL base, credenciales técnicas y secretos se administrarán mediante configuración segura y nunca desde el código fuente.
- La apertura del enlace, su resultado y los intentos denegados quedarán auditados sin copiar contenido clínico.

### Retiro completado de la implementación interna

- Se eliminaron las rutas, pantallas, servicios, repositorios, modelos, archivos locales y pruebas dedicados a la historia clínica interna.
- Se retiraron de pacientes los campos y operaciones destinados a alertas clínicas internas.
- Dado que la base sólo contenía datos de prueba descartables, se corrigieron las migraciones base y se ejecutó `migrate:fresh --seed` para obtener un esquema limpio.
- La base reconstruida no contiene las tablas `clinical_records`, `clinical_record_addenda` ni `clinical_record_attachments`, ni la columna `patients.clinical_alerts`.

## 8. Profesionales, agendas y consultorios

### Profesionales

- Una especialidad.
- Una matrícula.
- Una o más sedes.
- Duración de turno definida por profesional.

### Agenda

- Horarios semanales.
- Descansos.
- Feriados.
- Licencias.
- Excepciones por fecha.

### Consultorios

- Razón social y sede.
- Piso.
- Sector.
- Equipamiento.
- Restricciones operativas.
- Asignación fija o diaria.
- Asignación manual o automática.
- Los conflictos de profesional, horario o consultorio bloquean la operación.

## 9. Turnos

### Estados

- Reservado.
- Confirmado.
- En espera.
- Presente.
- Atendido.
- Cancelado.
- Ausente.

No se utilizará el estado `en atención`.

### Reglas

- El paciente sólo consulta sus turnos durante la primera etapa.
- En el futuro podrá habilitarse su autogestión.
- Se contemplarán:
  - Sobreturnos.
  - Lista de espera.
  - Bloqueos de agenda.
  - Turnos recurrentes.
  - Turnos presenciales.
  - Turnos virtuales.
  - Turnos de guardia.
  - Procedimientos.
- Cancelación permitida con al menos 24 horas de anticipación.
- Anticipos y pagos asociados dependerán del plan de prestaciones.

## 10. Guardia, internación y enfermería

### Circuitos

- Guardia e internación serán circuitos separados.
- Este sistema administrará únicamente datos operativos; toda documentación clínica se abrirá y registrará en la webapp externa.
- Se contemplarán:
  - Triage.
  - Prioridad.
  - Camas.
  - Habitaciones.
  - Sectores.
  - Ingresos.
  - Traslados.
  - Altas.

### Información clínica y medicación

- Las indicaciones, administraciones de medicamentos, alertas clínicas y resúmenes clínicos se gestionarán en la webapp externa.
- Este sistema podrá enlazar al contexto correspondiente, pero no duplicará la información clínica.
- La continuidad operativa ante la indisponibilidad de la webapp externa deberá acordarse con su responsable antes de habilitar estos circuitos.

## 11. Facturación, pagos y contabilidad

### Estado actual

- Implementado un MVP administrativo por razón social y sede: facturas, anticipos, conceptos con impuestos, pagadores paciente/cobertura/mixto, cuotas, cobros parciales o totales, caja por sede, arqueos y asientos automáticos.
- Las notas de crédito están limitadas al saldo aún no acreditado; las devoluciones requieren solicitud, autorización de Gerencia o Superadministración y registro posterior.
- Las anulaciones de comprobantes sin cobros generan su asiento inverso; los importes de una devolución registrada ajustan el saldo cobrado.
- Pendiente la emisión electrónica con ARCA, conciliación bancaria, administración de medios de pago, informes de período y definición fiscal definitiva.

- El sistema se preparará para una futura integración con ARCA.
- La responsabilidad de pago puede corresponder al paciente, su cobertura o ambos.
- Moneda inicial: pesos argentinos.
- Comprobante inicial: factura.
- Medios de pago:
  - Efectivo.
  - Tarjeta de débito.
  - Tarjeta de crédito.
  - Transferencia.
  - Billetera virtual.
  - Otros configurables.
- Se contemplarán:
  - Anticipos.
  - Cuotas.
  - Devoluciones.
  - Notas de crédito.
- El cierre diario podrá segmentarse por:
  - Caja.
  - Usuario.
  - Sede.
  - Medio de pago.

## 12. Planes de prestaciones

- Representan paquetes de prestaciones para pacientes.
- Podrán definir:
  - Nombre.
  - Precio.
  - Vigencia.
  - Renovación.
  - Beneficios.
  - Límites.
  - Prestaciones incluidas.
  - Anticipos.
  - Condiciones de pago.
- Algunos planes podrán generar cobros recurrentes.
- Un plan no se elimina físicamente: se archiva sin modificar contratos históricos.

## 13. Notificaciones

### Canales previstos

- Internas.
- Correo electrónico.
- WhatsApp.

### Eventos iniciales

- Recordatorios de turnos.

Las notificaciones clínicas serán responsabilidad de la webapp externa. Las demás integraciones externas se implementarán en una fase posterior.

## 14. Experiencia visual

- Identidad temporal: `Clínica`.
- Paleta principal: azules.
- Diseño simple basado principalmente en tarjetas.
- Adaptación completa a:
  - Escritorio.
  - Tablet.
  - Móvil.
- Modo claro y oscuro.
- Objetivo de accesibilidad: WCAG 2.2 nivel AA.

## 15. Seguridad y privacidad

### Marco legal asumido

- Jurisdicción: Ciudad Autónoma de Buenos Aires, Argentina.
- El paciente es titular de los datos de su historia clínica.
- La webapp clínica externa y los establecimientos deberán definir contractualmente las responsabilidades de depositario, custodio, encargado y responsable del tratamiento.
- La máxima autoridad del establecimiento deberá responder por las políticas de confidencialidad.
- La conservación mínima será de diez años desde la última actuación registrada.
- La conservación de la historia clínica será responsabilidad de la plataforma externa y no podrá reducirse por debajo del mínimo legal sin una revisión jurídica documentada.
- Antes de producción, un especialista deberá validar la matriz normativa aplicable.

### Estrategia de protección

Se aplicará defensa en profundidad:

1. Autorización denegada por defecto.
2. Permisos por rol, usuario, sede, razón social y contexto.
3. Autenticación multifactor planificada, pero no implementada en la primera fase.
4. Cierre automático de sesiones por inactividad.
5. TLS para datos en tránsito.
6. Cifrado de discos, base de datos y copias de seguridad.
7. Minimización de los datos administrativos y técnicos intercambiados con la webapp clínica.
8. Prohibición de incluir información clínica, credenciales, secretos o identificadores sensibles en URLs, notificaciones, logs técnicos o almacenamiento persistente del navegador.
9. Credenciales de integración almacenadas fuera del código y rotadas periódicamente.
10. Enlaces temporales, firmados y de un solo uso cuando el mecanismo de integración lo permita.
11. Datos sintéticos en ambientes no productivos.
12. Copias de seguridad cifradas, separadas e idealmente inmutables.

### Auditoría

La auditoría registrará:

- Inicios y cierres de sesión.
- Accesos fallidos.
- Consultas.
- Búsquedas.
- Creaciones.
- Modificaciones.
- Rectificaciones.
- Impresiones.
- Descargas.
- Exportaciones.
- Cambios de permisos.
- Acciones automáticas.
- Aperturas e intentos de acceso al enlace clínico externo.

El registro:

- Será append-only.
- No copiará contenido clínico ni respuestas clínicas de la webapp externa.
- Registrará actor, recurso, acción, fecha, origen, resultado, motivo y correlación.
- Tendrá acceso exclusivo del auditor.
- Auditará también las consultas realizadas sobre la propia auditoría.
- Conservará los registros durante un año y notificará por correo a los auditores activos antes de ejecutar la limpieza programada.
- Permitirá consultar y exportar la auditoría exclusivamente al auditor.

### Acceso excepcional de emergencia

Este sistema no implementará un mecanismo propio para omitir los controles clínicos de la plataforma externa. Si la webapp clínica ofrece acceso excepcional de emergencia, deberá exigir motivo, duración limitada, alerta, auditoría y revisión posterior. Este sistema podrá registrar que se inició el acceso, pero no decidirá ni ampliará el alcance clínico final.

## 16. Arquitectura por dominios y capas

### Principios

- La organización será por dominio funcional.
- Los controladores serán delgados.
- Los servicios contendrán casos de uso y reglas de aplicación.
- Los repositorios aislarán exclusivamente la persistencia.
- Los modelos representarán entidades y relaciones del dominio.
- Las vistas Vue no contendrán acceso directo a la base de datos.
- No se crearán repositorios o servicios genéricos sin un caso de uso concreto.
- Las dependencias apuntarán hacia contratos en los límites del dominio.

### Backend

```text
app/
  Domains/
    Users/
      Models/
      Controllers/
      Requests/
      Services/
      Repositories/
        Contracts/
        Eloquent/
      Policies/
    Professionals/
      ...
    Patients/
      ...
    Organizations/
      Models/
        OrganizationGroup
        LegalEntity
        Location
      ...
    ExternalClinicalRecords/
      Controllers/
      Services/
      Contracts/
database/
  factories/
  migrations/
  seeders/
```

Los nombres de carpetas PHP usarán `TitleCase` para respetar PSR-4. Los nombres visibles y las rutas continuarán en español.

`ExternalClinicalRecords` no tendrá modelos, migraciones ni repositorios de contenido clínico. Su responsabilidad se limitará a validar el acceso, construir o solicitar el enlace seguro, redirigir al usuario y auditar el resultado.

### Frontend

```text
resources/js/
  app.ts
  shared/
    components/
    composables/
    layouts/
    types/
  domains/
    auth/
      views/
      components/
      types/
    dashboard/
      views/
      components/
    users/
      views/
      components/
      types/
    professionals/
      views/
      components/
      types/
    patients/
      views/
      components/
      types/
    organizations/
      views/
      components/
      types/
```

### Flujo por capa

```text
Vista Vue
  -> Controlador
  -> Servicio / caso de uso
  -> Contrato de repositorio
  -> Implementación Eloquent
  -> Modelo
  -> MySQL
```

Las operaciones simples de sólo lectura podrán omitir un servicio únicamente cuando no exista lógica de aplicación; las integraciones externas y las operaciones de seguridad, agenda, facturación y auditoría deberán pasar por servicios explícitos.

El acceso externo seguirá el flujo `Vista de paciente -> controlador de integración -> servicio de autorización/enlace -> webapp clínica externa`. No existirá un flujo de persistencia clínica hacia MySQL.

## 17. Estado de fases y trabajo pendiente

1. Razones sociales y sedes: implementado, incluido el alcance de usuarios sobre múltiples sedes y razones sociales.
2. Usuarios y permisos configurables: implementados y aplicados en las policies; autenticación multifactor pendiente.
3. Profesionales: implementado el núcleo y la agenda avanzada con descansos, feriados por sede, licencias, excepciones por fecha, catálogo operativo de consultorios y asignación fija, manual o automática.
4. Pacientes y responsables: gestión administrativa y acceso externo básico implementados; vinculación federada o contextual con la webapp externa pendiente.
5. Turnos: núcleo, recurrencia, bloques de agenda y lista de espera implementados; sobreturnos, autogestión, turnos virtuales, de guardia y procedimientos pendientes.
6. Integración clínica externa: parcialmente implementada; el módulo interno fue retirado y existe un enlace HTTPS autorizado y auditado. Quedan pendientes la URL productiva y el contrato de autenticación/contexto con el proveedor.
7. Guardia e internación operativas: pendiente; la documentación clínica permanecerá en la webapp externa.
8. Enfermería operativa: pendiente; medicación e información clínica permanecerán en la webapp externa.
9. Facturación, pagos, cajas y contabilidad: MVP implementado con facturas, anticipos, cobros, cierres, notas de crédito, devoluciones y asientos automáticos; pendientes ARCA, conciliación, administración de medios de pago e informes de período.
10. Planes de prestaciones: pendiente.
11. Notificaciones administrativas externas: pendiente.
12. Auditoría avanzada: consulta, filtros, exportación, retención anual y aviso por correo implementados; monitoreo e interoperabilidad continúan pendientes.
13. Operación Docker: build, migraciones y login verificados; cola, scheduler, Mailpit y persistencia después de reinicios pendientes de prueba integral.

### Prioridades inmediatas

1. Definir con el proveedor el contrato técnico, autenticación, autorización, disponibilidad y soporte de la webapp externa.
2. Reemplazar la URL de demostración por la URL productiva y validar el flujo de extremo a extremo.
3. Implementar el mecanismo federado o temporal acordado sin exponer datos sensibles en la URL.
4. Completar la prueba integral Docker de cola, scheduler, Mailpit y persistencia tras reinicios.
5. Completar responsables de pago, menores/tutores y autogestión del paciente antes de planes de prestaciones y cobros recurrentes.
6. Evolucionar facturación con conciliación, informes de período y la integración ARCA una vez definidas las reglas fiscales.

## 18. Decisiones de acceso confirmadas

### Registro por invitación

- No existirá alta pública espontánea.
- El superadministrador iniciará la invitación.
- La invitación tendrá destinatario, vencimiento y uso único.
- El usuario invitado completará sus datos y contraseña desde un enlace firmado.
- La presentación visual, persistencia y envío de invitaciones están implementados.

### Acceso clínico de profesionales

- Un profesional podrá localizar a cualquier paciente.
- El enlace a la webapp clínica requerirá una relación asistencial vigente.
- La webapp externa será responsable de autenticar al usuario y autorizar el acceso clínico definitivo.
- Los intentos permitidos y denegados quedarán auditados sin copiar información clínica.
- El acceso excepcional de emergencia, si existe, será responsabilidad de la webapp externa.
