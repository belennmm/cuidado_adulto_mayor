# SCRUM-918 a SCRUM-922: recuperación y credenciales de prueba

Fecha: 2026-10-11. Tercer bloque de cinco subtareas de SCRUM-907. Se alcanzan 15 de las 26 subtareas previstas. Cambios locales, sin comandos Git, migraciones contra la base de trabajo ni envío real de correos.

| Subtarea | Resultado |
| --- | --- |
| SCRUM-918 | Login responde 401 con el mismo mensaje para cuenta inexistente, contraseña incorrecta y cuenta sin aprobación. Se calcula un hash de comprobación incluso sin cuenta. Registro responde 202 con un mensaje genérico y sin usuario, tanto para correo nuevo como existente; nunca sobrescribe una cuenta existente. Las búsquedas y límites normalizan el correo. |
| SCRUM-919 | Solicitud de recuperación siempre encolada, con respuesta 202 independiente de la existencia de la cuenta. Tokens del broker Laravel almacenados como hash, válidos durante 15 minutos, consumidos una sola vez dentro de una transacción con bloqueo de cuenta. Un nuevo token sustituye al anterior. No se inicia sesión ni se concede aprobación al restablecer. |
| SCRUM-920 | Eliminadas contraseñas fijas de los seeders de demostración, escaneo y rendimiento. Sin secreto explícito se genera una contraseña aleatoria de 32 caracteres para cada cuenta; no se imprime ni registra. Los scripts de ZAP/k6 requieren contraseñas explícitas. |
| SCRUM-921 | DatabaseSeeder y los tres seeders de cuentas rechazan cualquier entorno distinto de local/testing antes de escribir. Se mantiene habilitado UserObserver para que cambiar credenciales existentes también revoque sesiones y tokens. El catálogo MobilityExerciseSeeder no crea cuentas y permanece disponible. |
| SCRUM-922 | Pruebas nuevas de recuperación, enumeración, protección CSRF del flujo, revocación al restablecer y seguridad de seeders; se conservan las pruebas de expiración, rotación, logout y fuerza bruta de los bloques anteriores. |

## Contrato HTTP

- `POST /api/forgot-password`: únicamente `email`. Respuesta genérica 202. Límites independientes: 3 solicitudes/hora por correo y 10/hora por IP.
- `POST /api/reset-password`: `email`, `token`, `password`, `password_confirmation`. Token hexadecimal de 64 caracteres y política centralizada de contraseñas. Token incorrecto, cuenta inexistente y expiración devuelven el mismo mensaje 422. Límite de 5 intentos/minuto por correo y 10/minuto por IP, separado de login.
- Ambos requieren CSRF en el navegador, rechazan campos extra y no aceptan query de escritura. Formatos o contraseñas inválidas devuelven errores de validación sin consumir un token válido.
- `POST /api/register` cambia de 201 con datos del usuario a 202 con mensaje genérico. La interfaz existente admite este contrato; las pruebas se actualizaron para comprobar el estado real en base de datos.

El worker limita la emisión a un enlace por minuto y toma el mismo bloqueo de cuenta que el restablecimiento. El correo se entrega después de confirmar la transacción. Los rechazos de restablecimiento tienen un tiempo mínimo de respuesta para reducir diferencias observables. Las pruebas locales usan SQLite y verifican uso único y reemplazo; no simulan peticiones paralelas contra PostgreSQL.

## Configuración necesaria

`PASSWORD_RESET_URL` debe ser la URL fija del frontend, por ejemplo `https://tu-dominio/pages/reset-password.html`. Nunca se construye desde Host ni datos de la solicitud. El enlace transporta token/correo en el fragmento `#`, que no se envía al servidor al abrir la página. El frontend retira ese fragmento del historial inmediatamente y conserva el token sólo en memoria; no usa Web Storage y muestra mensajes con textContent.

Configurar un transporte de correo real, por ejemplo `MAIL_MAILER=smtp` y sus credenciales. Producción rechaza transportes log/array, desconocidos y cadenas failover/roundrobin que puedan terminar en un transporte que registre secretos. El ejemplo local conserva log para desarrollo; no entrega correos reales.

La solicitud utiliza siempre la conexión de cola `database`, aunque la conexión predeterminada sea sync. La tabla jobs ya pertenece a las migraciones existentes. Para entregar los correos hace falta mantener el worker:

```powershell
php artisan queue:work database --tries=3
```

Configurar los valores antes de generar la caché de configuración y reiniciar los workers después de cambiarla. Se mantiene la migración session_version requerida por el bloque 2; este bloque no añade migraciones.

## Seeders y herramientas

Variables opcionales de desarrollo: `DEMO_SEED_PASSWORD`, `SECURITY_SCAN_ADMIN_PASSWORD`, `SECURITY_SCAN_PROFESSIONAL_PASSWORD`, `SECURITY_SCAN_FAMILY_PASSWORD` y `PERFORMANCE_SEED_PASSWORD`. Deben satisfacer PasswordPolicy. Sin valores, las claves aleatorias no permiten entrar con las antiguas contraseñas conocidas. Los seeders no se ejecutaron sobre la base de trabajo.

ZAP y k6 de tres roles solicitan parámetros obligatorios `AdminPassword`, `ProfessionalPassword` y `FamilyPassword`; los transmiten al seeder del contenedor y al cliente de pruebas. El ejecutor de volumen de un rol solicita `Password` y presupone una cuenta previamente preparada. No guardar estos secretos en documentación ni en archivos versionados. No se ejecutaron Docker, ZAP ni pruebas de carga.

## Verificación

**596 pruebas backend / 7966 aserciones** y **191 pruebas frontend / 57 archivos**, sin fallos. Pint aplicado a 28 archivos PHP; guard estatico PHP y sintaxis de 73 archivos JavaScript / 3 scripts PowerShell aprobados. Se agregaron 30 casos backend y 4 frontend respecto del bloque anterior. Entorno instalado: PHP 8.2.12, Laravel 11.51, PHPUnit 11.5.55; Vitest 3.2.7 y jsdom 26.1. Los manifiestos declaran PHP 8.3 / Laravel 13 / PHPUnit 12 y Vitest 5 / jsdom 30. No se cambiaron dependencias; falta validar las versiones declaradas en CI.

Siguiente bloque: SCRUM-923 a SCRUM-927.
