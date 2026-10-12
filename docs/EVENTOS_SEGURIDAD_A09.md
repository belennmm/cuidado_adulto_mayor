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
