# SCRUM-928 a SCRUM-933: cierre de autenticacion y sesiones A07

Fecha: 2026-10-11. Ultimas seis subtareas de SCRUM-907: 26 de 26 implementadas entre los cinco bloques. Trabajo local, sin comandos Git. No se ejecutaron migraciones contra la base de trabajo.

| Subtarea | Implementacion |
| --- | --- |
| SCRUM-928 | Login, verificacion del segundo factor y administracion MFA consultan el estado actual bajo bloqueo de usuario. Sesiones y tokens vuelven a comprobar aprobacion, rol, existencia y huella en cada solicitud. Una cuenta pendiente o suspendida se representa mediante is_approved=false; no se introduce un estado paralelo. Cambios por SQL que omitan los observers tambien invalidan el acceso. |
| SCRUM-929 | Tokens API, desafios, huellas de sesion, codigos TOTP y recuperacion se comparan con hash_equals. Se comparan digests de longitud fija para secretos aleatorios; TOTP recorre siempre tres ventanas. No se afirma que consultas SQL o solicitudes completas tengan tiempo constante. |
| SCRUM-930 | Recuperacion de contrasena sin secretos en query ni fragmento: el correo presenta el codigo por separado y un enlace fijo al formulario. Cliente y servidor rechazan query con credenciales. dontFlash excluye contrasenas, tokens, desafios y codigos; errores API son genericos y su reporte conserva solo la clase de excepcion. Los canales de logging configurados aplican redaccion recursiva de contexto y mensajes. |
| SCRUM-931 | MFA con aplicacion autenticadora, desafio de login de cinco minutos, cinco intentos persistentes por desafio y bloqueo compartido por cuenta durante cinco minutos. TOTP de 30 segundos con tolerancia de un intervalo a cada lado y rechazo de reutilizacion. Configuracion pendiente: diez minutos y cinco intentos. Recuperacion de contrasena: quince minutos, uso unico y cinco intentos persistentes por token. Diez codigos MFA de recuperacion aleatorios de 128 bits, uso unico y caducidad de siete dias. |
| SCRUM-932 | Confirmar activacion o cambio de autenticador reemplaza todos los codigos de recuperacion. Desactivar los elimina; reactivar crea otros nuevos. Regeneracion explicita exige contrasena y segundo factor, elimina los anteriores y revoca todas las sesiones y desafios. Preparar un nuevo autenticador mantiene activo el anterior hasta confirmar el nuevo. |
| SCRUM-933 | Pruebas adicionales de MFA, expiracion, intentos, repeticion, rotacion, reactivacion, CSRF, cookies y cuentas invalidadas; se mantienen los casos existentes de inactividad, limite de dispositivos, aislamiento y autenticacion reciente. Frontend verifica segundo factor antes de guardar metadatos y muestra secretos solo como texto, sin Web Storage. |

## Flujo y contratos

MFA es configurable desde los tres perfiles. Para preparar el autenticador se confirma la contrasena actual; si MFA ya esta activo, se exige ademas un factor vigente. La clave se introduce manualmente en la aplicacion autenticadora: no se construyen URLs con secretos ni codigos QR que los incorporen. Confirmar devuelve los diez codigos una sola vez, cierra las sesiones y permite guardarlos antes de volver al login. No se almacenan en localStorage/sessionStorage; la interfaz permite ocultarlos. Si se acaba de usar un TOTP, se espera al siguiente o se usa un codigo de recuperacion.

El primer paso del login devuelve 202 y {mfa_required, challenge, expires_in} para cuentas con MFA. No devuelve usuario ni token y no autentica la cookie. El desafio en navegador queda ligado al hash de la sesion anonima y requiere CSRF; otra cookie o un cliente Bearer no puede completarlo. Tras verificar el factor se elimina el desafio, se rota la sesion/CSRF y se aplica el limite compartido de dispositivos. Para clientes API se emite el token solo despues del segundo factor.

| Metodo y ruta | Campos permitidos | Acceso |
| --- | --- | --- |
| POST /api/mfa/verify | challenge: hexadecimal minusculas de 64 caracteres; code: seis digitos o hexadecimal minusculas de 32 caracteres | Desafio vigente; CSRF en navegador; diez solicitudes/minuto por IP |
| GET /api/mfa | Ninguno | Autenticado; account:read; devuelve enabled y cantidad de codigos vigentes, nunca la clave |
| POST /api/mfa/setup | current_password; code opcional solo si MFA esta desactivado | Autenticado; account:write; contrasena actual; segundo factor si ya existe |
| POST /api/mfa/confirm | current_password, code | Autenticado; account:write; contrasena actual y TOTP de la clave pendiente |
| DELETE /api/mfa | current_password, code | Autenticado; account:write; contrasena actual y factor vigente |
| POST /api/mfa/recovery-codes | current_password, code | Autenticado; account:write; contrasena actual y factor vigente |

Las escrituras privadas comparten el limitador de reautenticacion: cinco solicitudes/minuto por cuenta y diez por IP. Los intentos de factores invalidos comparten ademas el bloqueo por cuenta; empezar otro desafio o cambiar de IP no lo evita. Todos estos contratos usan StrictFormRequest, rechazan campos desconocidos/query y no permiten modificar campos MFA por registro, perfil o asignacion masiva.

Solo un desafio de login queda pendiente por cuenta; iniciar otro invalida el anterior. La caducidad o el limite persistente de un desafio no se reinicia al vencer el limitador HTTP. Para recuperar una contrasena tras agotar cinco intentos debe solicitarse un nuevo codigo; la solicitud publica conserva su respuesta generica y sus limites existentes. Restablecer la contrasena revoca sesiones y desafios, y conserva MFA.

Las claves de autenticador usan el cast encrypted de Laravel; requieren conservar APP_KEY de forma segura. Desafios y codigos de recuperacion solo se almacenan como SHA-256. Consumo, configuracion y rotacion se serializan mediante transaccion y bloqueo de cuenta. Las respuestas API mantienen Cache-Control: no-store, private. Referencia del algoritmo y vectores verificados: [RFC 6238](https://www.rfc-editor.org/rfc/rfc6238), apendice B, SHA-1.

## Migraciones y operacion

Nueva migracion: backend/database/migrations/2026_10_11_000003_add_mfa_security.php. Agrega claves cifradas, estado y controles MFA, tablas de desafios/codigos y contador de intentos en password_reset_tokens. Debe aplicarse junto con las pendientes de los bloques anteriores antes de usar el codigo:

```powershell
cd backend
php artisan migrate
```

La base de trabajo no se modifico. La ampliacion de la huella de seguridad hace que credenciales anteriores a este despliegue requieran iniciar sesion nuevamente. Se mantienen las configuraciones de cookies, TLS, CSRF, colas y correo de los bloques anteriores. El correo de recuperacion ahora pide copiar el codigo en el formulario en vez de transportar credenciales en el enlace. En produccion, los logs del proxy/web server y herramientas externas tampoco deben registrar cuerpos o credenciales; la redaccion implementada corresponde a los canales de la aplicacion.

Inventario actualizado: 94 rutas HTTP, 90 API, siete API publicas y 83 privadas. No se duplicaron pruebas de los bloques anteriores cuando ya cubrian el comportamiento pedido.

## Validacion

**630 pruebas backend / 8650 aserciones** y **200 pruebas frontend / 59 archivos**, sin fallos. Este bloque agrega 18 casos backend y cuatro frontend respecto del bloque anterior. Pint aplicado a los 28 archivos PHP del bloque; sintaxis PHP de esos archivos y sintaxis de 71 archivos JavaScript correctas. Guard estatico PHP aprobado. Verificacion del HTML: formularios de credenciales y MFA separados, y controles MFA cargados una sola vez en cada uno de los tres perfiles.

Evidencia local en backend/storage/logs: a07-block5-backend-verified.txt y a07-block5-frontend-final.txt. El primer ensayo del caso de reactivacion fue rechazado correctamente por el limite compartido de operaciones; la prueba ahora avanza el reloj de pruebas antes de reactivar y la suite completa se repitio con exito. Las pruebas usan SQLite en memoria; no se ejecuto concurrencia contra PostgreSQL, migraciones de trabajo, seeders ni correos reales. No se instalaron dependencias. El entorno instalado conserva PHP 8.2.12 / Laravel 11.51 / PHPUnit 11.5.55 y Vitest 3.2.7 / jsdom 26.1; los manifiestos declaran PHP 8.3 / Laravel 13 / PHPUnit 12 y Vitest 5 / jsdom 30. La compatibilidad con esas versiones debe verificarse en CI.
