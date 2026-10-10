# SCRUM-791, SCRUM-792, SCRUM-793 y SCRUM-794

Historia SCRUM-779 (A01), sprint 9. Fecha: 2026-10-09. Bloque de cuatro subtareas. No se ejecutaron acciones de Git.

Seguimiento final: [SCRUM-795 y SCRUM-796](CIERRE_A01_INVALIDACION_METODOS.md) vincula tokens al estado de emisión. Un cambio externo de rol/aprobación ahora invalida la credencial con 401; los tokens anteriores sin huella requieren nuevo login. La API deja de aceptar sesiones web como alternativa al Bearer. Los resultados y respuestas descritos a continuación corresponden al bloque SCRUM-791 a SCRUM-794.

## SCRUM-791: permisos vigentes en cada solicitud

`ValidateCurrentAccess` se ejecuta después de la autenticación y antes de los controles de rol y del enlace de modelos. En cada solicitud privada vuelve a consultar al usuario; si hay un Bearer token, lo identifica por su valor actual y verifica su existencia, propietario y vencimiento. No reutiliza una identidad anterior si cambia el encabezado Authorization. Las Policies siguen comprobando la asignación vigente al recurso.

El rol y la aprobación proceden de la base de datos. Los valores enviados por el cliente en encabezados, cuerpo o información conservada de una sesión anterior no conceden permisos. La comprobación también cubre un cambio de estado realizado mediante Query Builder, aunque esa escritura no dispare los observadores de Eloquent. Si retiran la aprobación mediante SQL, las rutas privadas, incluido el perfil propio, rechazan el acceso con 403. Si las credenciales ya se eliminaron mediante los servicios controlados, su uso recibe 401.

Las pruebas conservan deliberadamente el guard entre solicitudes para comprobar la recarga de identidad, aprobación, rol, asignación, tokens eliminados o vencidos y alcance reducido. La comprobación consulta datos vigentes durante cada solicitud; no pretende bloquear cambios concurrentes que ocurran después de autorizar una operación.

## SCRUM-792: evitar descubrimiento por IDs secuenciales

Se mantienen los IDs numéricos y el contrato actual del frontend. El control de acceso impide que recorrer IDs vecinos permita distinguir recursos ajenos de inexistentes: ambos reciben 404 y el cuerpo `{"message":"Recurso no encontrado."}` dentro de un módulo autorizado. Los listados solo incluyen recursos asignados. Los módulos no permitidos rechazan con 403 antes de resolver identificadores.

Se corrigió además una diferencia en `medications.*.id` al editar un adulto: la validación global `exists` producía 422 para un ID inexistente, mientras un ID de otro adulto producía 404. Ahora se valida el formato del ID y el servicio lo busca dentro del adulto editado. Ambos casos reciben el mismo 404, con rollback de los cambios y sin crear medicamentos.

`SequentialResourceEnumerationTest` crea adultos con IDs consecutivos, alternando propietarios. Recorre los vecinos existentes y los siguientes IDs inexistentes por ruta, filtros y cuerpo; comprueba que solo el adulto asignado sea accesible y que los intentos no creen rutinas. Incluye los dos roles de cuidador y sus alias, y la variante de asignaciones anidadas de medicamentos. Esta cobertura comprueba respuestas y efectos persistidos, no igualdad de tiempos de ejecución.

## SCRUM-793: mínimos privilegios

### Credenciales y operaciones de la aplicación

El login emite tokens con capacidades explícitas y vencimiento; ya no emite `*`. `TokenAbilities` calcula las capacidades a partir del rol aprobado. El cliente no puede solicitar capacidades adicionales en el payload de login.

| Rol aprobado | Capacidades emitidas |
| --- | --- |
| Administrador | `account:read`, `account:write`, `care:read`, `care:write`, `admin:read`, `admin:write` |
| Profesional y alias | `account:read`, `account:write`, `care:read`, `care:write`, `professional:read`, `professional:write` |
| Familiar y alias | `account:read`, `account:write`, `care:read`, `care:write`, `family:read`, `family:write` |
| Rol desconocido o cuenta sin aprobación | Ninguna |

`EnsureTokenScope` exige la intersección entre las capacidades del token y las que permite el usuario vigente. GET/HEAD requieren lectura; las operaciones de modificación requieren escritura. `account` cubre perfil y logout; `admin` corresponde al middleware administrativo; `family` y `professional` cubren sus módulos, incluyendo la toma profesional de medicamentos. `care` cubre las operaciones compartidas, incluidos horarios y rutinas. Las Policies siguen imponiendo las restricciones específicas de cada operación y recurso.

Un token solo de lectura no puede modificar datos aunque su propietario sea administrador. Un token solo de cuenta no puede consultar datos de cuidado. Asignar capacidades administrativas a un token familiar no sustituye el permiso de rol. Promover una cuenta mediante una escritura externa tampoco agrega capacidades a un token emitido previamente. Los tokens heredados con `*` siguen siendo compatibles, pero se limitan por el rol, aprobación y Policies vigentes; los nuevos tokens del login siempre tienen capacidades explícitas.

Los servicios de movilidad y actualización/solicitud de cambio de horarios también autorizan mediante Gate. Se eliminó el reconocimiento administrativo alternativo de movilidad: `administrador` y un `admin` sin aprobación no tienen acceso administrativo. Los métodos internos sin actor siguen siendo invocados por controladores protegidos por middleware y Policy; no son endpoints públicos.

### Cuenta de base de datos y datos de demostración

En producción, `AppServiceProvider` valida la configuración efectiva de la conexión mediante el mismo parser de URLs del framework. Exige usuario y contraseña para conexiones de servidor y rechaza los nombres administrativos habituales `root`, `postgres` y `sa`, incluso si llegan mediante DB_URL/DATABASE_URL, codificación de URL o parámetros de consulta. SQLite no necesita credenciales de servidor. Los errores no incluyen contraseñas ni URLs.

`.env.example` propone `cuidado_runtime`. La cuenta de ejecución debe tener solo permisos sobre los datos, sesiones, caché y colas que utiliza la aplicación. Las migraciones deben usar una cuenta distinta con los permisos de esquema necesarios. Este cambio no crea cuentas ni modifica grants de un servidor existente: los permisos reales de una cuenta con otro nombre deben verificarse en la infraestructura; el nombre por sí solo no demuestra mínimos privilegios.

`InitialDataSeeder` y `SecurityScanSeeder` fallan antes de escribir si el entorno es producción. Esto impide instalar sus cuentas conocidas de demostración o escaneo mediante esos seeders. Se mantiene su funcionamiento explícito en desarrollo/pruebas. No se eliminaron ni alteraron cuentas de una base de datos existente.

Para desplegar, configurar primero una cuenta de ejecución distinta de los nombres bloqueados y con permisos limitados. La creación del administrador inicial en producción debe hacerse por un procedimiento controlado, sin estos seeders de demostración.

## SCRUM-794: asignación masiva de campos protegidos

`User::$fillable` se limita a nombre, correo y campos de contacto/perfil. Rol, aprobación, contraseña y consentimiento de privacidad dejan de poder asignarse mediante `fill`, `create` o `update` con arrays. Identificadores, timestamps y otros campos administrativos permanecen fuera de la lista. Las claves desconocidas se ignoran conforme al comportamiento actual del modelo.

Los servicios de registro, cambio de contraseña y administración escriben los atributos de seguridad explícitamente mediante `forceFill` después de seleccionar sus campos permitidos y derivar los valores del servidor. No pasan el payload completo del cliente a `forceFill`. El registro conserva la cuenta pendiente y los datos de consentimiento calculados por el servidor. Los seeders de desarrollo usan escrituras explícitas compatibles con esta protección.

Las listas de campos y construcciones explícitas de los demás servicios preservan propietarios y auditoría. Se agregaron comprobaciones directas del servicio de adultos para cargas anidadas: editar una asignación de medicamento no puede moverla a otro adulto, sustituir su ID de medicamento por una clave inyectada ni reescribir su stock mediante campos adicionales. La administración del stock conserva su operación específica. En ejercicios, `created_by` y `updated_by` proceden del actor y el cliente no puede sustituirlos.

Las dos pruebas anteriores que simulaban cambios internos de rol/aprobación ahora usan `forceFill(...)->save()` para representar una escritura autorizada; conservan sus comprobaciones de revocación. El observador sigue ejecutándose cuando esos atributos cambian realmente. Una llamada ordinaria a `update` con campos protegidos ahora los descarta y no constituye un cambio de seguridad.

## Validación

Archivos nuevos:

- `CurrentRequestPermissionsTest`: estado vigente e identidad entre solicitudes.
- `SequentialResourceEnumerationTest`: IDs vecinos, filtros, cuerpo y relaciones anidadas.
- `LeastPrivilegeTokensTest`: capacidades mínimas, servicios y bloqueo de seeders en producción.
- `MassAssignmentProtectionTest`: modelo, servicios, auditoría y compatibilidad de seeders de prueba.
- `RuntimeDatabaseAccountTest`: configuración directa y por URL de las credenciales del servidor.

Desde `backend`:

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/CurrentRequestPermissionsTest.php tests/Feature/SequentialResourceEnumerationTest.php tests/Feature/LeastPrivilegeTokensTest.php tests/Feature/MassAssignmentProtectionTest.php tests/Unit/RuntimeDatabaseAccountTest.php --colors=never
php vendor/phpunit/phpunit/phpunit --colors=never
```

Se agregaron **48 casos de prueba**. La suite completa pasó con **302 pruebas y 3720 aserciones**. Pint aplicó el formato del proyecto a los 21 archivos PHP de esta entrega. Las pruebas utilizan SQLite en memoria; los seeders se ejecutaron solamente sobre las bases efímeras de pruebas. PHPUnit y Pint se ejecutaron fuera del aislamiento por las restricciones locales de Windows.

La validación local usa PHP 8.2.12, Laravel 11.51 y PHPUnit 11.5.55. `composer.json` declara PHP 8.3+, Laravel 13 y PHPUnit 12; queda pendiente comprobar esas versiones. No se modificaron dependencias ni credenciales locales.

SCRUM-795 y SCRUM-796 se completaron en el [bloque final](CIERRE_A01_INVALIDACION_METODOS.md).
