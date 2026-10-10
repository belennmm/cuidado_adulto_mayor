# Cierre de SCRUM-779 (A01): SCRUM-795 y SCRUM-796

Fecha: 2026-10-09. Entrega final de la historia de permisos del backend. No se ejecutaron acciones de Git.

## SCRUM-795: invalidación inmediata del acceso

Se conserva la revocación central del observador de usuarios: cambiar rol o contraseña, retirar aprobación o eliminar la cuenta mediante Eloquent elimina sus tokens, sesiones de base de datos y credenciales de restablecimiento. Las operaciones administrativas y de perfil controladas conservan sus transacciones. La suspensión utiliza el estado existente `is_approved = false`.

Cada nuevo token incorpora ahora una huella de su estado de emisión: ID del usuario, rol, aprobación y hash de contraseña. `AccountSecurityState` calcula la huella; el modelo `App\Models\PersonalAccessToken`, registrado en Sanctum, la asigna desde la base de datos al crear la credencial. No es un campo asignable desde el payload ni se incluye en la serialización del token.

`ValidateCurrentAccess` compara esa huella con el estado vigente antes de autorizar la solicitud. Si el token no tiene huella o esta no coincide, elimina las credenciales desactualizadas y rechaza el acceso con 401. Esto también impide reutilizar tokens cuando una escritura directa a la base de datos cambia rol, aprobación o contraseña sin disparar observadores. La limpieza preserva tokens emitidos para el estado nuevo: presentar un token antiguo no puede revocar los tokens vigentes obtenidos mediante un login posterior al cambio.

Tras detectar el cambio y eliminar los tokens antiguos, volver a aprobar la cuenta o restaurar su rol no recupera esos tokens. Las modificaciones ordinarias del perfil no invalidan credenciales. Las credenciales de otros usuarios se conservan.

La API autentica mediante Bearer tokens; `sanctum.guard` queda vacío para impedir que una sesión web sustituya una credencial revocada. El frontend actual utiliza el token del login. Las sesiones de base de datos también se limpian en las operaciones de revocación.

La invalidación del acceso se aplica en la siguiente solicitud privada. Los cambios efectuados por los servicios revocan al guardar. Para SQL externo, la huella detecta diferencias en el estado observado; no registra cambios que se hagan y reviertan completamente entre solicitudes. Las escrituras administrativas deben usar los servicios controlados para garantizar revocación al producirse el cambio. Tampoco se cancela una operación ya autorizada que esté en ejecución.

### Migración y despliegue

Se agregó `2026_10_09_000001_add_security_fingerprint_to_personal_access_tokens.php`. Añade una columna nullable de 64 caracteres y no atribuye artificialmente el estado actual a credenciales antiguas.

Aplicar esta migración como parte del despliegue, antes de servir solicitudes con el código nuevo. Los tokens existentes sin huella requerirán un nuevo login y serán rechazados al usarse. Las nuevas credenciales seguirán respetando sus capacidades y vencimiento. No se ejecutó la migración contra la base de datos local del usuario ni contra producción; las pruebas la ejecutan sobre SQLite en memoria. La configuración publicada debe reflejar el cambio de `sanctum.guard` si el despliegue usa caché de configuración.

## SCRUM-796: métodos HTTP en rutas sensibles

`SensitiveRouteMethodsTest` obtiene todas las rutas API privadas registradas y construye una matriz GET, HEAD, POST, PUT, PATCH y DELETE. Consulta al router para resolver cada combinación real de URL y método: una URL estática, como la del calendario, puede coincidir con una ruta parametrizada bajo otro método. Esto evita dar por no registrado un método que sí resuelve otro endpoint protegido.

| Caso | Comprobación |
| --- | --- |
| Invitado y método registrado | 401 para todas las rutas privadas |
| Método sin endpoint para esa URL | 405; no ejecuta controladores |
| Administrador, profesional, familiar y alias en módulos no permitidos | 403 en los métodos registrados |
| HEAD | Mantiene los controles de la ruta GET |
| `X-HTTP-Method-Override` y `_method` | El método efectivo mantiene autenticación y autorización |
| TRACE, CONNECT y PROPFIND | 405 y mensaje genérico en las URLs privadas |
| OPTIONS/preflight | 204 vacío, sin datos del recurso ni modificaciones |
| Sesión web sin Bearer | No sustituye la credencial de la API |

Las pruebas comprueban además que los intentos no modifiquen adultos, usuarios, horarios o vacaciones. Los flujos autorizados y las restricciones de propiedad siguen cubiertos por la suite previa.

## Validación reproducible

Desde `backend`:

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/ImmediateAccessInvalidationTest.php tests/Feature/SensitiveRouteMethodsTest.php --colors=never
php vendor/phpunit/phpunit/phpunit --colors=never
```

Los dos archivos nuevos pasaron con **23 casos y 2464 aserciones**. La suite completa pasó con **325 pruebas y 6188 aserciones, sin fallos**. Pint verificó los 11 archivos PHP de esta entrega. Las expectativas del bloque anterior para cambios externos de rol/aprobación pasan de 403 a 401, porque ahora se invalida la credencial completa.

La validación local usa PHP 8.2.12, Laravel 11.51 y PHPUnit 11.5.55. El proyecto declara PHP 8.3+, Laravel 13 y PHPUnit 12; queda pendiente validar esas versiones. No se modificaron dependencias. PHPUnit y Pint se ejecutaron fuera del aislamiento por las restricciones locales de Windows.

## Cobertura final de la historia

| Subtareas | Entrega |
| --- | --- |
| SCRUM-781 a SCRUM-783 | Inventario de endpoints, autenticación privada y roles del servidor |
| SCRUM-784 a SCRUM-786 | Policies, propiedad/asignación por ID y denegación por defecto |
| SCRUM-787 a SCRUM-790 | Campos protegidos, revocación, respuestas sin revelar recursos e IDOR/escalamiento |
| SCRUM-791 a SCRUM-794 | Estado vigente por solicitud, enumeración, mínimos privilegios y asignación masiva |
| SCRUM-795 y SCRUM-796 | Invalidación de credenciales por estado de emisión y matriz de métodos HTTP |

La implementación y sus pruebas cubren las 16 subtareas de SCRUM-779. La actualización del estado de Jira, los commits y el despliegue quedan a cargo del usuario. Los permisos efectivos de la cuenta de base de datos del servidor y la validación en las versiones declaradas requieren comprobación en su entorno correspondiente.
