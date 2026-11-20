# Pruebas de seguridad y operación

Este documento detalla las pruebas manuales y automatizadas necesarias para validar la implementación de autenticación multifactor (MFA) y sus controles operativos asociados.

## Alcance

La implementación cubre:

- MFA obligatorio mediante TOTP para los roles `superadmin`, `administrator`, `manager`, `accounting` y `auditor`.
- Enrolamiento inicial y confirmación del segundo factor.
- Códigos de recuperación de un solo uso.
- Vencimiento y límite de intentos del desafío.
- Prevención de reutilización de códigos TOTP.
- Revocación de MFA y sesiones ante suspensión, cambio de rol y restablecimiento de contraseña.
- Auditoría de los eventos de seguridad relevantes.

## Roles y cuentas de prueba

Preparar estas cuentas, con contraseña conocida y estado activo:

| Cuenta | Rol | MFA esperado |
| --- | --- | --- |
| `mfa.superadmin` | Superadministrador | Obligatorio |
| `mfa.administrador` | Administrador | Obligatorio |
| `mfa.gerente` | Gerente | Obligatorio |
| `mfa.contabilidad` | Contabilidad | Obligatorio |
| `mfa.auditor` | Auditor | Obligatorio |
| `sin-mfa.recepcion` | Recepción | No obligatorio |
| `operador.admin` | Administrador o Superadministrador | Gestión de usuarios y suspensión |

Usar una aplicación autenticadora compatible con TOTP, por ejemplo Google Authenticator, Microsoft Authenticator, 1Password o Authy. Cada prueba debe realizarse en una sesión o ventana privada nueva para no reutilizar el estado de sesión anterior.

## Preparación del entorno

1. Aplicar las migraciones de la aplicación.
2. Confirmar que `MFA_CHALLENGE_TTL_MINUTES`, `MFA_MAX_ATTEMPTS` y `MFA_RECOVERY_CODES` tengan los valores esperados.
3. En entornos productivos o de preproducción, verificar que la aplicación se sirva por HTTPS y que `SESSION_SECURE_COOKIE=true` y `SESSION_ENCRYPT=true` estén definidos.
4. Configurar el reloj del dispositivo de prueba en hora automática. TOTP depende de que la diferencia de tiempo sea pequeña.
5. Tener acceso a la pantalla de auditoría para el rol Auditor o Superadministrador.

## Casos de prueba funcionales

### 1. Inicio de sesión de un rol sin MFA obligatorio

1. Iniciar sesión con `sin-mfa.recepcion`.
2. Marcar y desmarcar la opción de mantener la sesión iniciada en ejecuciones separadas.

Resultado esperado:

- El sistema ingresa directamente al dashboard, sin mostrar pantallas MFA.
- El evento `auth.login_succeeded` queda registrado.

### 2. Desafío MFA para roles obligatorios

1. Iniciar sesión con `mfa.superadmin` sin tener un factor configurado.
2. Ingresar correo y contraseña válidos.

Resultado esperado:

- No se accede al dashboard después de validar la contraseña.
- Se redirige a la pantalla de configuración de MFA.
- La opción “mantener sesión iniciada” no habilita una sesión persistente para un rol con MFA obligatorio.

### 3. Enrolamiento TOTP inicial

1. Desde la pantalla de configuración, agregar la clave mostrada a la aplicación autenticadora. Si la aplicación admite enlaces `otpauth`, usar la URL proporcionada.
2. Ingresar el código de seis dígitos actual.
3. Confirmar la activación.

Resultado esperado:

- Se generan los códigos de recuperación.
- Los códigos se muestran una sola vez.
- Al confirmar que fueron guardados, se inicia sesión y se accede al dashboard.
- Se registra el evento `auth.mfa_enrolled` y, al completar el acceso, `auth.login_succeeded`.

### 4. Inicio de sesión con un factor ya configurado

1. Cerrar sesión de `mfa.superadmin`.
2. Iniciar sesión nuevamente con credenciales válidas.
3. Ingresar el código vigente de la aplicación autenticadora.

Resultado esperado:

- Se muestra la pantalla de verificación MFA, no la de enrolamiento.
- El acceso al dashboard ocurre solamente después de validar el código.
- Se registra `auth.mfa_challenge_succeeded` y `auth.login_succeeded`.

### 5. Código TOTP inválido

1. Iniciar el desafío MFA de una cuenta enrolada.
2. Ingresar un código incorrecto.

Resultado esperado:

- La sesión no se autentica.
- Se informa que el código es inválido o no puede reutilizarse.
- Se incrementa el contador de intentos.
- Se registra `auth.mfa_challenge_failed` con resultado denegado.

### 6. Reutilización de un código TOTP

1. Iniciar sesión con un código TOTP válido.
2. Cerrar sesión inmediatamente, antes de que se genere el próximo código.
3. Intentar iniciar sesión usando exactamente el mismo código.

Resultado esperado:

- El segundo intento es rechazado.
- El sistema no permite reutilizar el mismo paso temporal, incluso si el código aún parece vigente en la aplicación autenticadora.
- Se registra un fallo de desafío MFA.

### 7. Límite de intentos

1. Iniciar un desafío MFA.
2. Ingresar códigos incorrectos hasta alcanzar `MFA_MAX_ATTEMPTS`.
3. Intentar enviar un código adicional.

Resultado esperado:

- Al superar el límite, se invalida el desafío.
- El usuario debe volver a iniciar sesión con correo y contraseña.
- No debe ser posible continuar utilizando la misma URL de desafío.

### 8. Vencimiento del desafío

1. Iniciar sesión hasta llegar a la pantalla MFA.
2. Esperar más de `MFA_CHALLENGE_TTL_MINUTES` sin completar el código.
3. Enviar un código correcto.

Resultado esperado:

- El desafío se rechaza por vencimiento.
- El usuario debe iniciar sesión nuevamente.

### 9. Uso de un código de recuperación

1. Iniciar el desafío MFA de una cuenta enrolada.
2. Seleccionar “Usar un código de recuperación”.
3. Ingresar uno de los códigos guardados durante el enrolamiento.

Resultado esperado:

- El acceso se completa correctamente.
- El código queda marcado como utilizado y no vuelve a ser válido.
- Se registra `auth.mfa_recovery_code_used`.

### 10. Reutilización o error en código de recuperación

1. Intentar ingresar el mismo código de recuperación ya usado, o uno inexistente.

Resultado esperado:

- El acceso se rechaza.
- Se incrementa el contador de intentos del desafío.
- Se registra `auth.mfa_recovery_code_failed` con resultado denegado.

### 11. Bloqueo de sesiones previas sin MFA

1. Crear una sesión válida de una cuenta que luego pasará a un rol con MFA obligatorio.
2. Con `operador.admin`, cambiar su rol a uno obligatorio, por ejemplo Administrador.
3. Volver a la sesión original e intentar navegar a cualquier pantalla protegida.

Resultado esperado:

- La sesión anterior se invalida o exige un inicio de sesión nuevo.
- No debe poder accederse al dashboard ni a módulos protegidos sin completar MFA.

### 12. Cambio de rol y revocación del factor

1. Usar una cuenta con MFA configurado.
2. Desde `operador.admin`, modificar su rol.
3. Intentar acceder con una sesión existente y luego volver a iniciar sesión.

Resultado esperado:

- Las sesiones existentes del usuario objetivo quedan invalidadas.
- Se rota el token de “recordarme”.
- Si el nuevo rol requiere MFA, se solicita nuevamente el enrolamiento.
- Se registra `auth.mfa_revoked`.

### 13. Suspensión de usuario

1. Mantener una sesión abierta de una cuenta con MFA.
2. Desde `operador.admin`, suspender esa cuenta.
3. Intentar seguir navegando con la sesión abierta e intentar un nuevo inicio de sesión.

Resultado esperado:

- La sesión existente deja de tener acceso.
- Un nuevo inicio de sesión se rechaza debido al estado suspendido.
- Se revocan MFA, códigos de recuperación y sesiones del usuario.
- Se registran `auth.mfa_revoked` y el evento de suspensión de usuario.

### 14. Restablecimiento de contraseña

1. Solicitar y completar el restablecimiento de contraseña para una cuenta con MFA configurado.
2. Intentar iniciar sesión con la nueva contraseña.

Resultado esperado:

- Los factores MFA y códigos de recuperación previos quedan revocados.
- La cuenta que requiera MFA debe enrolar nuevamente su aplicación autenticadora.
- Las sesiones anteriores no siguen siendo válidas.

### 15. Protección de rutas

1. Con una cuenta MFA obligatoria, completar solo correo y contraseña pero no el desafío MFA.
2. Intentar abrir directamente `/dashboard` u otra URL protegida desde otra pestaña.

Resultado esperado:

- No se entrega información protegida.
- El sistema redirige al inicio de sesión o a la verificación según el estado válido de la sesión.

## Verificación de auditoría

Con el rol Auditor o Superadministrador, revisar la bitácora luego de las pruebas. Validar que cada evento incluya, cuando corresponda, actor, usuario afectado, fecha, IP, agente de usuario, resultado y motivo.

| Acción | Evento esperado | Resultado |
| --- | --- | --- |
| Enrolamiento confirmado | `auth.mfa_enrolled` | `success` |
| TOTP correcto | `auth.mfa_challenge_succeeded` | `success` |
| TOTP incorrecto o reutilizado | `auth.mfa_challenge_failed` | `denied` |
| Código de recuperación correcto | `auth.mfa_recovery_code_used` | `success` |
| Código de recuperación incorrecto | `auth.mfa_recovery_code_failed` | `denied` |
| Revocación por contraseña, rol o suspensión | `auth.mfa_revoked` | `success` |
| Acceso completado | `auth.login_succeeded` | `success` |

## Pruebas automatizadas recomendadas

Ejecutar la suite específica de MFA y luego la suite completa:

```powershell
php artisan test --compact tests/Feature/MfaAuthenticationTest.php
php artisan test --compact
```

La prueba específica debe verificar como mínimo:

- Redirección al desafío para roles obligatorios después de credenciales correctas.
- Bloqueo de sesiones preexistentes sin la marca de verificación MFA.
- Consumo único de un código de recuperación.

Para despliegues de producción, complementar las pruebas de aplicación con una validación de HTTPS, cabeceras de seguridad, cifrado de sesiones, ejecución del scheduler, worker de colas, retención de respaldos y monitoreo de errores.
