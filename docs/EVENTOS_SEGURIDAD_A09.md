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
