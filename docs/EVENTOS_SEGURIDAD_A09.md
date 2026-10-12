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
