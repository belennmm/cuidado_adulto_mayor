# Eventos de seguridad A09

## SCRUM-957: formato común

`App\Support\SecurityEvent` define el contrato de los eventos. `toArray()` y la
serialización JSON producen la misma estructura, con `schema_version` igual a 1.

| Campo | Contenido |
| --- | --- |
| `event` | Identificador del evento, por ejemplo `authentication.login`. |
| `action` | Operación realizada, por ejemplo `login`. |
| `result` | Resultado de la operación, por ejemplo `denied`. |
| `actor_id` | Identificador interno del usuario, o `null` si no se conoce. |
| `resource_type` | Tipo de recurso afectado, o `null`. |
| `resource_id` | Identificador del recurso afectado, o `null`. |
| `occurred_at` | Fecha UTC ISO 8601 con microsegundos y sufijo `Z`. |
| `ip_address` | IP de la solicitud, o `null` si no aplica. |
| `correlation_id` | Identificador de correlación, o `null` si no está disponible. |

Los campos `event`, `action` y `result` son obligatorios y no aceptan valores vacíos.
El contrato recibe identificadores explícitos; no recibe cuerpos de solicitudes ni
modelos completos. La fecha se genera al crear el evento y se normaliza a UTC.

Esta primera entrega define la estructura. La generación del identificador de
correlación, la escritura de logs y la integración con las operaciones del sistema
se incorporan en las siguientes subtareas.

Prueba desde `backend`:

```powershell
php vendor/phpunit/phpunit/phpunit tests/Unit/SecurityEventTest.php --testdox
```

## SCRUM-958: correlación por solicitud

El middleware global `AssignCorrelationId` genera un UUID antes de validar el
tamaño y el método de la solicitud. El servidor genera un valor nuevo para cada
solicitud e ignora cualquier `X-Correlation-ID` enviado por el cliente.

El identificador está disponible en `$request->attributes->get('correlation_id')`
para incorporarlo al campo homónimo de `SecurityEvent`. Se devuelve también en el
header `X-Correlation-ID`, incluso en respuestas de error renderizadas por Laravel.
No se guarda como estado global, por lo que no se reutiliza entre solicitudes.

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/RequestCorrelationIdTest.php --testdox
```

## SCRUM-959: autenticación, bloqueos y autorización

`AuthenticationAuditService` registra el resultado final de las respuestas API:

| Evento | Situación | Resultado |
| --- | --- | --- |
| `authentication.login` | Login exitoso, credenciales rechazadas, cuenta pendiente o bloqueo. | `success`, `denied` o `blocked` |
| `authentication.logout` | Cierre de sesión exitoso. | `success` |
| `authentication.required` | Acceso rechazado con HTTP 401. | `denied` |
| `authorization.denied` | Acceso rechazado con HTTP 403. | `denied` |
| `security.rate_limit` | Otras solicitudes limitadas con HTTP 429. | `blocked` |

Cada evento utiliza el formato común, el ID de correlación, la IP y la plantilla
de ruta (sin valores de parámetros ni query string). El actor del login solo se
asigna tras una autenticación exitosa. No se copian los cuerpos de solicitud o
respuesta, contraseñas, correos ni tokens.

El middleware y el manejador de excepciones comparten una marca por solicitud
para registrar una sola vez el resultado. Por ahora se escribe `security.event`
con nivel `info` en el canal predeterminado. La separación de canales y los niveles
de severidad se incorporan en SCRUM-966 y SCRUM-971, respectivamente.

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/AuthenticationAuditTest.php --testdox
```

## SCRUM-960: acceso y cambios en datos sensibles

`AuditSensitiveData` registra respuestas API exitosas de los controladores de
adultos mayores, rutinas, notas, incidentes, ejercicios de movilidad, recordatorios
y administración de medicamentos. Incluye consultas de adultos, rutinas e
incidentes desde las vistas familiar y profesional.

Se emite `sensitive_data.access` para lecturas y `sensitive_data.change` para
creaciones, actualizaciones y eliminaciones. Se conserva el actor, el tipo e ID
del recurso, la fecha, IP y correlación. En creaciones se obtiene únicamente el ID
del recurso devuelto; en colecciones el ID es `null`. Las consultas de incidentes
de un adulto usan el tipo `older_adult_incidents` y el ID del adulto.

No se registran nombres, diagnósticos, contenido de notas, valores anteriores o
nuevos, términos de búsqueda ni listas de pacientes. Las respuestas fallidas no
se registran como cambios exitosos; las denegaciones 401/403 y bloqueos 429 siguen
cubiertos por SCRUM-959. Este registro refleja operaciones HTTP completadas, no
modificaciones directas de base de datos fuera de la API.

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/SensitiveDataAuditTest.php --testdox
```

## SCRUM-961: usuarios, roles, cuentas y medicamentos

`AuditAccountMedicationChanges` registra operaciones HTTP exitosas de creación,
edición, aprobación, rechazo y eliminación de usuarios; registro público y edición
del perfil; creación, edición, ajuste de stock y eliminación de inventario.

Los eventos son `user.create/update/approve/reject/delete`,
`account.create/update` y `medication_inventory.create/update/adjust_stock/delete`.
Una edición administrativa que cambia efectivamente el rol, aprobación o
contraseña genera además `user.role_changed`, `account.status_changed` o
`account.credentials_changed`, respectivamente. Estos eventos adicionales
comparten el ID de correlación de la misma operación; no contienen valores
anteriores o nuevos. El registro público tiene actor `null` e ID de la cuenta creada.

Los cambios se identifican después de guardar el usuario, mediante `wasChanged`.
Los rechazos de validación no generan eventos de cambio exitoso. No se registran
nombres, correos, contraseñas, dosis ni contenido de los cuerpos HTTP. El ID del
inventario corresponde a `older_adult_medications`, no al catálogo `medications`.
Los ajustes de aumento y disminución usan la acción común `adjust_stock`.

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/AccountMedicationAuditTest.php --testdox
```

## SCRUM-962: acciones administrativas, exportaciones y eliminaciones

`AuditAdministrativeActions` completa la cobertura de respuestas API exitosas:
consultas y operaciones en `api/admin/*` generan `administrative.action` cuando
no tienen ya un evento específico. Incluye creación y edición de turnos, revisión
de solicitudes de cambio, vacaciones y consultas administrativas. Las eliminaciones
sin un evento previo generan `resource.deleted`.

La auditoría existente de usuarios, inventario y datos sensibles se conserva sin
agregar un segundo evento genérico. Los turnos y vacaciones usan el tipo e ID de
su recurso; las consultas generales usan la plantilla de ruta, sin query string.

Las respuestas administrativas con `Content-Disposition: attachment` generan
`administrative.export`. El backend actual no tiene endpoints de exportación:
esta detección queda preparada y se verifica mediante una ruta exclusiva de prueba,
que no se agrega a las rutas de producción. Registra la respuesta de descarga
preparada por el servidor, no confirma que el cliente haya recibido todo el archivo.
No guarda el nombre del archivo ni ejecuta o copia su contenido para auditarlo.

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/AdministrativeAuditTest.php --testdox
```

## SCRUM-963: contexto uniforme en todos los eventos

Los cuatro servicios de auditoría delegan la escritura a `SecurityEventRecorder`.
Este punto común genera la fecha UTC y obtiene la IP y correlación de la solicitud
actual. La correlación se toma del atributo generado por el servidor, nunca del
header aportado por el cliente.

Cada llamada exige actor, acción, resultado, tipo e ID del recurso. Si el actor o
recurso no se conocen, el campo permanece presente con valor `null`. Todos los
eventos incluyen la misma versión y las mismas diez claves del esquema; no se
omite contexto porque el usuario sea anónimo. No se mantiene contexto estático o
compartido entre solicitudes.

Las pruebas verifican el esquema completo, valores conocidos y desconocidos,
direcciones IPv4/IPv6 y el instante de creación en UTC. Las pruebas de los cuatro
servicios verifican que la centralización conserva sus eventos y correlación.

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/SecurityEventRecorderTest.php --testdox
```

## SCRUM-964: exclusión de secretos y contenido médico

Antes de escribir cualquier evento, `SecurityEventRecorder` aplica
`SecurityEventPrivacy`. Esta política permite exclusivamente las diez claves del
esquema. Descarta claves adicionales, incluyendo metadatos libres, contraseñas,
tokens, cookies y cuerpos de solicitud/respuesta, aunque contengan objetos anidados.
Los valores compuestos dentro de campos permitidos se sustituyen por `null`.

Los nombres de eventos, acciones, resultados y tipos deben ser identificadores
técnicos; los recursos solo admiten IDs numéricos o plantillas de rutas API.
Las rutas con query string y los valores personales fuera de formato no se guardan.
La IP debe ser válida y la correlación debe tener formato UUID. Se mantienen las
claves con `null` cuando un valor no cumple el contrato.

Esta política se aplica a los eventos de seguridad escritos por el recorder;
no reemplaza el tratamiento de errores ni filtra logs ajenos a este flujo.
Los identificadores técnicos son proporcionados por los servicios de auditoría,
nunca deben construirse a partir de texto libre del usuario.

```powershell
php vendor/phpunit/phpunit/phpunit tests/Unit/SecurityEventPrivacyTest.php tests/Feature/SecurityEventRecorderTest.php --testdox
```

## SCRUM-965: enmascaramiento de información personal

El filtro central reduce la precisión de la IP antes de escribir el evento:

| Dirección recibida | Dirección registrada | Prefijo conservado |
| --- | --- | --- |
| `192.0.2.123` | `192.0.2.0` | IPv4 /24 |
| `2001:db8:abcd:1234::10` | `2001:db8:abcd::` | IPv6 /48 |
| `::ffff:192.0.2.123` | `192.0.2.0` | IPv4 /24 |

Las direcciones IPv4 representadas dentro de IPv6 se normalizan y reciben la misma
protección. Los nombres, correos, teléfonos y datos clínicos continúan excluidos;
no se agregan campos con versiones originales o completas de la IP.

Se conservan los IDs internos del actor/recurso y la correlación para investigar
eventos. Este enmascaramiento reduce exposición, pero no convierte los registros
en datos anónimos. Tampoco modifica la solicitud original: los controles de acceso
y límites de intentos siguen utilizando la IP original. La IP registrada identifica
una red, no permite distinguir dispositivos dentro del mismo prefijo.

```powershell
php vendor/phpunit/phpunit/phpunit tests/Unit/SecurityEventPersonalDataMaskingTest.php --testdox
```

## SCRUM-966: separación de logs operativos y auditoría

Todos los eventos de `SecurityEventRecorder` se escriben exclusivamente en el canal
`security_audit`, archivo `backend/storage/logs/security-audit.log`. El canal usa
JSON por línea y bloqueo de escritura. Su nivel mínimo `info` es independiente del
`LOG_LEVEL` operativo, para no descartar eventos por tener `LOG_LEVEL=warning`.

Los mensajes operativos continúan utilizando el canal predeterminado (`LOG_CHANNEL`),
sin copiar los eventos de auditoría allí. El archivo de auditoría permanece bajo
`storage`, fuera de `public`, y está excluido de Git por el `.gitignore` de logs.
No se agrega `security_audit` al stack operativo.

Una prueba escribe archivos temporales reales para comprobar la separación,
estructura JSON y enmascaramiento. Las pruebas de los cuatro servicios también
exigen que la escritura seleccione explícitamente el canal de auditoría.
La protección y centralización adicionales, rotación y retención se completan
en SCRUM-967 y SCRUM-968.

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/SecurityAuditChannelTest.php --testdox
```

## SCRUM-967: centralización y protección contra alteraciones

El archivo de auditoría usa permisos `0600` en sistemas POSIX y bloqueo de escritura.
En Windows se deben aplicar ACL al directorio de logs para permitir lectura solo
al servicio y al personal autorizado. `storage` debe permanecer fuera del acceso web.

Cada línea incluye `extra.audit_integrity`: versión, algoritmo HMAC-SHA256,
identificador de clave y firma. La firma protege el mensaje, canal, nivel y contexto
completo del evento, incluida su fecha `occurred_at`. El verificador detecta cambios
de esos campos y registros sin firma. No detecta eliminación o reordenamiento de
líneas completas, ni impide falsificación si se compromete la clave.

En producción se requiere `SECURITY_AUDIT_INTEGRITY_KEY` aleatoria de al menos 32
bytes (admite prefijo `base64:`), almacenada como secreto fuera de Git y de los logs.
`SECURITY_AUDIT_KEY_ID` identifica la clave, no contiene el secreto. Para desarrollo
y pruebas, si no se configura una clave dedicada se usa `APP_KEY`. Conservar las
claves anteriores de forma segura para verificar archivos históricos.

Desde `backend`, verificar un archivo con su clave correspondiente:

```powershell
php artisan security:audit-verify
php artisan security:audit-verify storage/logs/security-audit.log
```

Para envío central configurar `SECURITY_AUDIT_COLLECTOR_URL=tls://host:puerto` con
un colector que acepte JSON por línea sobre TLS. Se verifica certificado y nombre
del servidor; no se aceptan TCP plano, certificados autofirmados, credenciales o
rutas en la URL. Primero se escribe la copia local firmada y luego se envía la misma
línea al colector con límites de tiempo de 2 segundos. Si falla el envío, se conserva
la copia local y se emite un aviso operativo sin datos del evento. No hay reintentos
persistentes: se debe configurar un agente de recolección para reenviar el archivo
local si se requiere entrega garantizada.

La centralización remota queda implementada y configurable; no se ha conectado a
un servidor real en este entorno. Se debe desplegar el colector con cuenta y permisos
independientes de la aplicación y almacenamiento de solo anexado o inmutable.
Los archivos previos a este cambio no tienen firma y fallarán la verificación;
conservarlos identificados como históricos sin firmar.

## SCRUM-968: rotación y retención

La auditoría rota diariamente a `storage/logs/security-audit-YYYY-MM-DD.log`.
Conserva JSON, firmas, bloqueo de escritura y permisos restrictivos. El verificador
sin argumento usa el archivo diario actual; para históricos se indica su ruta.
El archivo anterior `security-audit.log` no se elimina automáticamente.

`SECURITY_AUDIT_RETENTION_DAYS=90` admite enteros entre 1 y 365; una configuración
inválida impide iniciar, en vez de desactivar el límite. Monolog limita la cantidad
de archivos al rotar. Además, la limpieza por antigüedad conserva hoy y los N-1 días
anteriores, incluso si hubo días sin actividad. No toca logs operativos, enlaces,
directorios, fechas inválidas, archivos futuros ni archivos con otro nombre.

Desde `backend`, revisar primero y ejecutar después:

```powershell
php artisan security:audit-prune --dry-run
php artisan security:audit-prune
php artisan security:audit-verify
```

La limpieza está programada a las 02:00 en la zona horaria de la aplicación y evita
ejecuciones simultáneas. En producción es necesario habilitar el scheduler de
Laravel (`php artisan schedule:run` cada minuto) con permisos sobre estos archivos;
no se ha instalado una tarea del sistema en este entorno. Los procesos persistentes
deben reiniciarse tras cambiar la configuración. La retención del colector remoto
se configura por separado. Ajustar el plazo a la política institucional y preservar
evidencias necesarias antes de su vencimiento.

## SCRUM-969: alertas de actividad sospechosa

Cada evento auditado alimenta contadores temporales: `security.alert.brute_force`
para logins denegados o bloqueados por IP, `security.alert.denials` para accesos
sin autenticación o autorización por usuario (por IP si no hay usuario), y
`security.alert.mass_access` para lecturas exitosas de datos sensibles por usuario.
Se cuenta una vez por regla y solicitud, no por registro devuelto. Una alerta indica
un patrón que requiere revisión, no demuestra un ataque ni bloquea por sí misma.

Valores iniciales: ventana de 60 segundos; umbrales de 10, 10 y 100 solicitudes,
respectivamente; pausa entre alertas de 300 segundos por regla y origen. Ajustar
`SECURITY_ALERT_WINDOW_SECONDS`, `SECURITY_ALERT_COOLDOWN_SECONDS`,
`SECURITY_ALERT_BRUTE_FORCE`, `SECURITY_ALERT_DENIALS` y
`SECURITY_ALERT_MASS_ACCESS` según el tráfico esperado. Se admiten enteros entre
1 y 86400; valores inválidos impiden iniciar. Las ventanas empiezan con el primer
evento y no son deslizantes. Un login exitoso no cuenta como fallo ni borra fallos.

Los contadores usan el rate limiter de Laravel y la pausa usa la caché de la
aplicación. En producción ambos deben usar almacenamiento compartido persistente
con incrementos y `add` atómicos (por ejemplo Redis o base de datos); `array` solo
sirve para pruebas y no coordina procesos. Las claves contienen HMAC del usuario/IP,
nunca contraseñas, correos ni IP completa en claro. Los contadores expiran.

Las alertas se escriben con nivel `warning` en el canal firmado `security_audit`,
con actor, IP enmascarada y correlación de la solicitud que alcanzó el umbral.
También llegan al colector si está configurado. No se han configurado correo,
SMS ni un SIEM real: el responsable debe monitorizar ese canal. Si la caché falla,
se conserva el evento base y se emite `security_alert_detection_unavailable` en
el log operativo; la detección queda degradada y requiere intervención.

## SCRUM-970: pruebas y procedimiento de respuesta

`SecurityAlertResponseTest` recorre endpoints reales con el middleware activo y
escritura JSON firmada: logins fallidos, denegaciones por usuario, lecturas sensibles
con cambio de IP, éxito público, falla de caché y alteración de evidencia. Complementa
las pruebas de umbral, expiración, aislamiento por IP y pausa de `SecurityAlertTest`.

El procedimiento manual y la matriz de ensayo están en
[RESPUESTA_ALERTAS_A09.md](RESPUESTA_ALERTAS_A09.md). Incluyen responsables,
preservación y verificación de evidencia, investigación, contención autorizada,
recuperación y cierre. La entrega a un colector y la respuesta humana requieren
un ensayo en staging; no se presentan como verificadas por estas pruebas locales.

## SCRUM-971: severidad consistente

`SecurityEventSeverity` centraliza la clasificación. La severidad se guarda en
`level_name` de Monolog, ya protegido por la firma; no modifica el esquema de
contexto ni se toma de campos enviados por el cliente.

| Nivel | Criterio | Ejemplos |
| --- | --- | --- |
| `info` / informativo | Actividad normal, incluidos cambios autorizados. | Login válido, lecturas, actualización de perfil o rol autorizada. |
| `warning` / advertencia | Resultado `denied`, `blocked` o `triggered`, salvo la regla crítica. | Acceso prohibido, bloqueo de login, alertas de fuerza bruta o múltiples denegaciones. |
| `error` / error | Resultado `failure` o `error`. | Falla de caché que deja la detección degradada. |
| `critical` / crítico | Alerta `security.alert.mass_access` con resultado `triggered`. | Umbral de lecturas sensibles alcanzado; posible exposición que requiere revisión prioritaria. |

Una alerta crítica no confirma una filtración ni bloquea cuentas automáticamente.
Los fallos operativos de detección conservan el aviso operativo seguro y ahora
también generan un registro firmado `security.alert.detection_unavailable` de nivel
`error`, sin recursión ni detalles de la excepción. Los eventos HTTP de error no
auditados por los servicios actuales no se registran automáticamente por este cambio.
El canal admite desde `info`, de modo que conserva los cuatro niveles. Ajustar la
prioridad de monitoreo del colector sin descartar la evidencia de menor severidad.

## SCRUM-972: protección contra inyección en logs

Los campos de texto con CR, LF, tabulaciones, bytes nulos, controles C0/C1,
separadores Unicode de línea/párrafo, controles bidireccionales o UTF-8 inválido
se sustituyen por `null`; no se concatenan ni se corrigen para parecer datos válidos.
Se mantienen las listas de valores permitidos, identificadores tipados y fechas UTC
canónicas válidas. La versión de esquema la fija el servidor.

El canal `security_audit` aplica `SanitizeSecurityAuditLog` antes de firmar, incluso
en escrituras directas que no usen el recorder. Solo admite los mensajes fijos
`security.event` y `security.alert`, descarta contexto ajeno al esquema y elimina
`extra` previo; después se agrega únicamente la firma. El JSON por línea evita
interpretar datos como registros independientes. El colector usa el mismo registro
saneado y firmado. No se cambia el formato de los logs operativos.

Las pruebas cubren controles y UTF-8 inválido en todos los campos de texto, fechas
inválidas y escrituras reales al canal con intentos de crear líneas falsas o filtrar
datos mediante contexto/extra. Comprueban cantidad de líneas, JSON válido y firma
verificable después del saneamiento. Esto no protege contra código comprometido que
reemplace el canal o contra un atacante con acceso a la clave de firma.
