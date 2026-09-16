# Registro de cambios de Charlie2

Este archivo reconstruye la evolución funcional del proyecto a partir de los chats vinculados, las migraciones, las rutas y el código disponible. Como esta copia no contiene un repositorio Git reconocible ni versiones publicadas, las fechas corresponden a las entregas registradas en las conversaciones y no a *releases* etiquetadas.

Las funcionalidades que fueron reemplazadas se conservan en su fecha histórica y se señalan en la entrada posterior que las retiró.

## Sin publicar

### Pendiente conocido

- Resolver la discrepancia del dashboard: la interfaz entrega métricas sintéticas en cero mientras una prueba automatizada espera otros valores.
- Definir el contrato productivo con la webapp clínica externa, incluida la autenticación, la autorización final y el contexto seguro del paciente.
- Aplicar efectivamente los permisos individuales en las políticas; actualmente se almacenan, pero la autorización se basa principalmente en roles y relación asistencial.

## 7 de septiembre de 2026

### Documentación

- Se reemplazó el README genérico por una guía de instalación y continuidad del proyecto.
- Se incorporaron documentos de arquitectura, operación y traspaso.
- Se contrastaron rutas, servicios, políticas, configuración y frontend para registrar el estado implementado y las tareas pendientes.
- Se documentaron límites operativos de la integración clínica externa, permisos individuales, auditoría y recuperación de datos cifrados.

### Verificación

- Se verificaron 53 rutas de aplicación.
- Pasaron el chequeo de TypeScript y la compilación de producción.
- Pasaron 147 de 148 pruebas; quedó registrado el fallo conocido del dashboard.

## 24 de agosto de 2026

### Cambiado

- La historia clínica dejó de administrarse dentro de Charlie2 y pasó a considerarse responsabilidad exclusiva de una webapp externa.
- La ficha del paciente quedó con una acción autorizada para abrir esa plataforma externa.
- El acceso externo se limitó a profesionales con relación asistencial y se incorporó auditoría de accesos permitidos, denegados y errores de configuración.
- La URL externa quedó centralizada mediante `EXTERNAL_CLINICAL_RECORD_URL` y restringida a HTTPS, sin incluir DNI ni identificadores del paciente.

### Eliminado

- Se retiraron el dominio, las rutas, las pantallas, los servicios, los repositorios, las pruebas y el almacenamiento de historia clínica interna.
- Se eliminaron las atenciones, adendas, adjuntos y alertas clínicas internas del esquema y de la interfaz.
- Se reconstruyó la base de datos descartable con las migraciones vigentes y los seeders iniciales.

### Verificación

- Pasaron las 7 pruebas focalizadas del acceso clínico externo, con 43 aserciones.
- El esquema resultante quedó sin tablas clínicas ni la columna `patients.clinical_alerts`.

## 4 de agosto de 2026

### Agregado

- Se habilitó el envío por correo de invitaciones firmadas, temporales y de un solo uso para usuarios, profesionales y pacientes.
- Los pacientes nuevos comenzaron a crear una cuenta vinculada con rol Paciente y correo obligatorio.
- Se agregó inicialmente un módulo interno de historia clínica con atenciones firmadas e inmutables, adjuntos privados y rectificaciones versionadas.
- Se incorporó un disco privado configurable para adjuntos mediante `CLINICAL_RECORDS_ROOT`, conservando rutas relativas y compatibilidad con archivos existentes.

### Cambiado

- El CUIT pasó a validarse por formato de 11 dígitos y unicidad, sin la regla `ValidArgentineCuit`.

### Seguridad

- El contenido clínico interno se entregaba fuera de las props de Inertia y con `no-store`.
- El acceso se denegaba al paciente y se autorizaba únicamente a profesionales activos con una relación asistencial basada en turnos admitidos.

> Este módulo clínico interno fue retirado el 24 de agosto de 2026 y no forma parte del estado actual.

## 31 de julio de 2026

### Agregado

- Se incorporó la gestión de razones sociales y sedes, con archivo, restauración y validaciones de integridad.
- Se agregó el núcleo de turnos con creación, edición, cancelación, estados y prevención de superposiciones.
- Se vincularon los horarios profesionales con sedes.
- El formulario de turnos pasó a usar una fecha y franjas horarias calculadas según agenda y duración del profesional.
- Los selectores de pacientes y profesionales incorporaron búsqueda por apellido.

## 29 de julio de 2026

### Agregado

- Se implementó autenticación y registro exclusivamente mediante invitaciones del superadministrador.
- Se agregó la administración de usuarios con roles, permisos persistidos, suspensión, restauración y reinvitación.
- Se incorporó la gestión de profesionales, especialidad, matrícula, duración de turnos y horarios de atención.
- Se agregó el ABM administrativo de pacientes con contactos, cobertura, estados, archivo lógico y restauración.
- Se incorporó control de concurrencia optimista para evitar sobrescrituras silenciosas en pacientes.
- Se creó la base de auditoría *append-only* con actor, origen, correlación y snapshots cifrados.
- Se protegió la relación entre profesionales y cuentas de usuario contra eliminaciones permanentes inconsistentes.

### Interfaz

- Se consolidó el frontend Vue 3, TypeScript, Inertia y Tailwind CSS con navegación por dominios, modo claro/oscuro y diseño adaptable.
- Se incorporó el dashboard administrativo inicial con métricas sintéticas.
