# SCRUM-923 a SCRUM-927: dispositivos y autenticación reciente

Fecha: 2026-10-11. Cuarto bloque de cinco subtareas de SCRUM-907: 20 de 26 subtareas. Cambios locales, sin comandos Git ni migraciones ejecutadas sobre la base de trabajo.

| Subtarea | Resultado |
| --- | --- |
| SCRUM-923 | El restablecimiento de contraseña revoca cookies, tokens API y registros de dispositivos mediante UserObserver/CredentialRevocationService. Se amplían las pruebas para sesiones almacenadas en archivos y para conservar el acceso de otras cuentas. |
| SCRUM-924 | Límite compartido de cinco sesiones por usuario, configurable con SESSION_MAX_PER_USER. Incluye navegador y tokens API, incluso los creados mediante User::createToken. Al excederlo se revoca la sesión con actividad más antigua. Registro y aplicación del límite usan una transacción con bloqueo de usuario. |
| SCRUM-925 | Registro persistente account_sessions con UUID público, tipo de sesión, IP, User-Agent limitado a 512 caracteres, creación, última actividad, expiración y fecha de autenticación. La actividad se actualiza en el servidor. El UUID público no autentica; no se expone la cookie, su identificador, el hash del token ni el ID del token API. |
| SCRUM-926 | Listado y cierre individual disponibles en los tres perfiles. Sólo se consultan o cierran sesiones de la propia cuenta. Una sesión ajena y una inexistente responden el mismo 404. Cerrar la actual termina el acceso; cerrar otra conserva la actual. Se mantienen CSRF, capacidades account:read/account:write y límites generales. |
| SCRUM-927 | Para guardar datos personales, incluido correo y contraseña, se exige autenticación de los últimos cinco minutos, configurable con SESSION_REAUTHENTICATION_TIMEOUT. Fuera del plazo se responde 428 reauthentication_required sin guardar cambios. Puede confirmarse la contraseña en el formulario de perfil o mediante el nuevo formulario de identidad. La confirmación queda ligada al dispositivo actual. |

## API

- `GET /api/sessions`: devuelve sessions con id, type (browser/api), device, ip_address, last_activity, created_at y current. Requiere autenticación y account:read para tokens restringidos.
- `DELETE /api/sessions/{accountSession}`: UUID del listado, sin query ni cuerpo de negocio. Devuelve mensaje y current. Requiere autenticación y account:write; CSRF para navegador.
- `POST /api/reauthenticate`: únicamente current_password. Requiere autenticación, CSRF del navegador y account:write. Valida la contraseña actual contra el usuario vigente y actualiza sólo authenticated_at de la credencial que realizó la solicitud.
- `PUT /api/me`: conserva el contrato de perfil y añade la comprobación de autenticación reciente. Enviar current_password permite confirmar y guardar en la misma solicitud. Cambiar contraseña sigue exigiendo current_password, confirmación y PasswordPolicy aunque el login sea reciente.

Reautenticación y actualización de perfil usan el limitador existente: cinco solicitudes/minuto por cuenta y diez por IP. Un error de contraseña no renueva el plazo. Navegar o actualizar la última actividad tampoco renueva authenticated_at. La comprobación y escritura del perfil se realizan bajo bloqueo de usuario, comprobando además que la huella de seguridad siga vigente.

Los datos de User-Agent son orientativos y pueden ser falsificados por el cliente. La interfaz los inserta con textContent. La IP proviene de Request::ip con la configuración de proxies de la aplicación; no se interpreta User-Agent/IP como autorización ni se vincula rígidamente una sesión a una IP móvil.

## Migración y compatibilidad

Nueva migración: `backend/database/migrations/2026_10_11_000002_create_account_sessions_table.php`. Es necesario aplicarla junto con las migraciones pendientes del bloque anterior antes de usar este código:

```powershell
php artisan migrate
```

La migración registra tokens API existentes sin atribuirles una autenticación reciente: podrán confirmar su contraseña si necesitan modificar datos personales. Las cookies anteriores a la migración no tienen registro y requerirán iniciar sesión nuevamente. Los registros de tokens se eliminan en cascada al revocarlos. Un token antiguo invalidado por cambios externos de permisos no puede borrar el registro de un token nuevo válido ni cerrar una nueva sesión del navegador. La revocación selectiva compara una huella interna de seguridad, bajo bloqueo de cuenta; esa huella no se devuelve al cliente.

El registro central permite cerrar cookies almacenadas en database o file. Para sesiones de archivo no se necesita localizar ni recorrer archivos por usuario: una cookie cuyo registro se eliminó se rechaza en la siguiente solicitud. Se mantiene session_version para la revocación global y se almacena sólo SHA-256 del identificador de la sesión del navegador, sin exponerlo en la API.

Sesiones inactivas durante 15 minutos o que alcancen su expiración absoluta se depuran del registro. La actividad nunca prolonga la expiración absoluta. Los tokens API también quedan sujetos a la inactividad y conservan cualquier expires_at más corto que el límite general. El límite se aplica por usuario, no entre cuentas distintas.

Producción rechaza límites de sesiones y plazos de reautenticación no positivos. Valores por defecto:

```dotenv
SESSION_MAX_PER_USER=5
SESSION_REAUTHENTICATION_TIMEOUT=5
```

No se ejecutaron migraciones, seeders, correos reales, Docker ni operaciones Git sobre el entorno de trabajo. Las migraciones se probaron en la base SQLite en memoria de PHPUnit. El bloqueo concurrente está implementado para bases con bloqueo de filas; no se ejecutó una prueba paralela contra PostgreSQL.

## Verificación

**612 pruebas backend / 8224 aserciones** y **196 pruebas frontend / 58 archivos**, sin fallos. Se agregaron 16 casos backend y 5 frontend respecto del bloque anterior. Pint aplicado a 23 archivos PHP; guard estatico PHP aprobado y sintaxis de 70 archivos JavaScript verificada. Se verifico la presencia unica de los controles de sesiones en los tres perfiles. Se conservan las limitaciones de versiones del entorno de los bloques anteriores: instalado PHP 8.2.12 / Laravel 11.51 / PHPUnit 11.5.55 y Vitest 3.2.7 / jsdom 26.1; manifiestos PHP 8.3 / Laravel 13 / PHPUnit 12 y Vitest 5 / jsdom 30. No se sustituyeron dependencias; falta verificar esas versiones declaradas en CI.

Las subtareas SCRUM-928 a SCRUM-933 se completaron posteriormente en el [bloque final de A07](SESIONES_AUTENTICACION_A07_BLOQUE5.md).
