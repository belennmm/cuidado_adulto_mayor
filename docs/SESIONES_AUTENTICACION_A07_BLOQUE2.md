# SCRUM-913 a SCRUM-917: revocación, contraseñas y límites de login

Fecha: 2026-10-11. Segundo bloque de cinco subtareas de SCRUM-907. Cambios locales; sin comandos Git.

| Subtarea | Resultado |
| --- | --- |
| SCRUM-913 | Logout invalida la sesión actual; contraseña, rol y cualquier cambio de aprobación revocan las credenciales de la cuenta. La huella incluye una versión de seguridad creciente para impedir que restaurar privilegios reactive cookies anteriores. |
| SCRUM-914 | `POST /api/logout-all`, autenticado y limitado por cuenta/IP, requiere `current_password`. Revoca todas las sesiones, tokens API y tokens de recuperación de la propia cuenta, incluida la sesión actual. Formularios disponibles en el perfil de administrador, familiar y profesional. |
| SCRUM-915 | Política centralizada para registro, creación/edición administrativa y cambio de contraseña: 15–128 caracteres, mayúsculas, minúsculas, números y símbolos. Se rechazan reutilización de la contraseña actual y caracteres nulos. Con bcrypt se rechazan más de 72 bytes para evitar truncamientos; Argon2 conserva el máximo de 128 caracteres. |
| SCRUM-916 | Rechazo local de patrones comunes y consulta de contraseñas filtradas mediante Pwned Passwords. Una coincidencia con frecuencia positiva se rechaza. Fallos de conexión, redirecciones o respuestas inválidas impiden guardar la contraseña nueva y devuelven 503 genérico. |
| SCRUM-917 | Se conservan 20 solicitudes de login/minuto por IP y 5 fallos por pareja cuenta/IP. Se añaden límites independientes de 10 fallos por cuenta y 30 por IP, en ventanas de 300 segundos. Alternar IP o cuenta no evita los límites independientes. |

## Migración necesaria antes de usar el código

La migración `backend/database/migrations/2026_10_11_000001_add_session_version_to_users.php` agrega `users.session_version`, con valor inicial 0. **No se ejecutó contra la base de trabajo.** Después de preparar el entorno habitual, se aplica desde backend con:

```sh
php artisan migrate
```

La nueva huella de seguridad exige iniciar sesión nuevamente después del despliegue: las sesiones y tokens emitidos con la huella anterior quedan rechazados. `session_version` está oculto y fuera de los campos asignables del modelo y de los payloads admitidos. La revocación incrementa el contador en el servidor, elimina credenciales y conserva la versión sincronizada del modelo para que un guardado posterior no sobrescriba una revocación más reciente.

Las sesiones de base de datos se eliminan físicamente. Los otros almacenamientos del servidor rechazan la huella obsoleta al recibir la siguiente solicitud. La invalidación no depende de que el identificador de sesión haya desaparecido del dispositivo. Se comprobó también con el driver `file`.

El logout habitual conserva las otras sesiones. “Cerrar todas las sesiones” verifica la contraseña vigente dentro de una transacción, no admite un identificador de otra cuenta y exige CSRF en el navegador. Solo tras la confirmación del servidor el frontend limpia los metadatos y navega al login. Los errores permiten reintentar y borran el campo de contraseña.

## Comprobación de contraseñas comprometidas

`PASSWORD_CHECK_COMPROMISED=true` es el valor predeterminado. Producción rechaza que se desactive. Para pruebas se desactiva la consulta externa en `phpunit.xml`; las pruebas específicas la activan y simulan respuestas y fallos mediante `Http::fake`.

Se calcula SHA-1 únicamente para consultar filtraciones; el almacenamiento de contraseñas continúa usando el hasher configurado, no SHA-1. Solo se envían los cinco primeros caracteres del hash a un endpoint HTTPS fijo, con `Add-Padding: true`. No se envían contraseña, correo ni hash completo. El cotejo del sufijo ocurre localmente; los registros de relleno con frecuencia 0 se ignoran. Se almacenan en caché rangos públicos por una hora, nunca contraseñas ni hashes completos de los usuarios. Límites de conexión/respuesta: 2/4 segundos; no se siguen redirecciones.

Referencia del protocolo: [API oficial de Pwned Passwords](https://haveibeenpwned.com/API/V3#PwnedPasswords). Las pruebas verifican el prefijo exacto enviado y el encabezado de relleno; no se envían credenciales reales durante las pruebas.

La política también se aplica dentro de los servicios para impedir que una llamada interna evite las validaciones HTTP. Los formularios de registro y administración conservan espacios de las contraseñas sin recortarlos. El login no impone la política nueva a las contraseñas existentes: se aplica al registrar o cambiar la contraseña.

## Límites

Los límites de fallos están en `backend/config/abuse.php`. La cuenta se identifica mediante hash del correo normalizado a minúsculas y sin espacios externos. El éxito limpia el contador de la pareja, conservando los fallos de cuenta e IP hasta que caduquen. Los 429 incluyen `retry_after` y `Retry-After`.

La confirmación de “cerrar todas” tiene un límite adicional de 5 solicitudes/minuto por cuenta y 10 por IP. El logout normal permanece disponible aunque se agote el límite de operaciones de escritura. Los contadores usan el caché configurado de Laravel; las réplicas de producción deben compartir ese almacenamiento para conservar límites globales. El comportamiento del limitador se apoya en la [API oficial de Laravel](https://laravel.com/framework/docs/rate-limiting).

## Validación y alcance

Pruebas de revocación entre varios dispositivos, aislamiento entre cuentas, conservación de otras sesiones con logout normal, CSRF/contraseña obligatorios, rechazo de cookies anteriores con drivers database/file, versiones crecientes y reautenticación después de revocar. Pruebas de política en registro, administración, perfil y servicios; contraseñas comunes, débiles, reutilizadas y filtradas; relleno de respuestas, caché por prefijo, respuestas inválidas y desconexiones; límites frente a rotación de IP/cuenta y expiración de ventanas. Pruebas frontend de cierre global confirmado por servidor y manejo de errores.

Resultados finales: **566 pruebas backend / 7836 aserciones** y **187 pruebas frontend / 56 archivos**, sin fallos. Pint verificó los 27 archivos PHP modificados y pasó el guard estático de inyección PHP. Se verificó la sintaxis de 69 archivos JavaScript y la estructura de las tres páginas de perfil. Se mantiene la limitación de dependencias locales documentada en [el primer bloque](SESIONES_AUTENTICACION_A07.md): el entorno instalado difiere de las versiones declaradas y requiere validación adicional en CI.

Con este bloque se cubren 10 de las 26 subtareas. SCRUM-918 en adelante quedan para las entregas siguientes.
