# SCRUM-1018: verificación de errores del servidor en la API

## Bloques para commits manuales

| Bloque | Subtareas | Entrega |
|---|---|---|
| 1 | SCRUM-1020 Provocar respuestas 500; SCRUM-1021 Inspeccionar las respuestas de la API | Pruebas automatizadas de fallos y contrato HTTP. |
| 2 | SCRUM-1022 Revisar los registros del sistema; SCRUM-1023 Confirmar que no se expongan datos clínicos, tokens, SQL ni trazas internas | Pendiente: inspección explícita de registros y controles de exposición de información sensible. |

Mensaje sugerido para el primer commit: `SCRUM-1020 SCRUM-1021: probar errores 500 y verificar respuestas de la API`.

## Fallos controlados (SCRUM-1020)

`backend/tests/NonFunctional/Reliability/ApiServerErrorResponseTest.php` registra rutas únicamente dentro de la aplicación efímera de PHPUnit. No agrega endpoints de diagnóstico al código de rutas de la aplicación ni desconecta servicios reales. `RefreshDatabase` usa SQLite en memoria según `phpunit.xml`.

Se provocan seis variantes de error:

| Variante | Cómo se provoca |
|---|---|
| Excepción de aplicación | Se lanza `RuntimeException`. |
| Error SQL real en testing | SQLite intenta consultar una tabla sintética inexistente. |
| Error de tipo PHP | Se pasa un array a `strlen`. |
| Error HTTP explícito | Se ejecuta `abort(500)`. |
| Excepción con respuesta propia | Se lanza `HttpResponseException` con HTML y un `Content-Length` incorrecto. |
| Respuesta directa | Una ruta devuelve HTML con estado 500 y un `Content-Length` incorrecto. |

Cada variante se ejecuta con `app.debug` activado/desactivado y con `Accept: application/json`, `Accept: text/html` o sin `Accept`: 36 casos. Se comprueba además que `/api/ping` siga respondiendo después de cada fallo.

Otros seis casos recorren el endpoint real `GET /api/incidents?date=2026-10-20`, con usuarios aprobados de los roles administrador, profesional y familiar. Se introduce un fallo de aplicación o de base de datos en `IncidentListingService` mediante un mock limitado a la prueba. Se confirma HTTP 500 y que las cantidades de usuarios, adultos e incidentes y el registro de incidente existente permanezcan intactos. Esto verifica un fallo de lectura controlado, no una interrupción real de PostgreSQL ni recuperación de infraestructura.

Un caso adicional hace dos solicitudes fallidas con el mismo `X-Correlation-ID` proporcionado por el cliente y comprueba que el servidor genere identificadores diferentes.

## Inspección de respuestas (SCRUM-1021)

Las respuestas se inspeccionan a través del kernel HTTP real de Laravel, con su manejo de excepciones y middleware habilitados. El contrato comprobado es:

- Estado HTTP 500, conservado aunque el cliente solicite HTML o no envíe `Accept`.
- Cuerpo JSON exacto: `{"message":"Error interno del servidor."}`.
- `Content-Type: application/json` y `X-Content-Type-Options: nosniff`.
- `Cache-Control: no-store, private`.
- `X-Correlation-ID` con UUID generado por el servidor.
- Ausencia de un `Content-Length` obsoleto; si existe, debe coincidir con la longitud real del cuerpo normalizado.

El comportamiento ya estaba implementado en `bootstrap/app.php` y `SecurityHeaders`; este bloque añade cobertura de regresión sin cambiar ese contrato. Las rutas temporales y mocks sólo existen durante PHPUnit.

## Ejecución y evidencia

Desde `backend`:

```powershell
php vendor/phpunit/phpunit/phpunit --filter ApiServerErrorResponseTest
```

Para revisar también las regresiones existentes del manejo de errores, correlación y encabezados:

```powershell
php vendor/phpunit/phpunit/phpunit --filter 'ApiServerErrorResponseTest|InjectionAndErrorDisclosureTest|RequestCorrelationIdTest|SecurityHeadersAndCorsTest|IncidentPersistenceTest'
```

Resultado local del comando de regresión: **73 pruebas y 570 aserciones aprobadas**, incluyendo los 43 casos nuevos. Runtime disponible: PHP 8.2.12 y PHPUnit 11.5.55. Este resultado corresponde a las dependencias locales instaladas; `composer.json` declara PHP ^8.3 y PHPUnit ^12, por lo que queda pendiente repetir en el runtime declarado del proyecto.

No se han ejecutado operaciones de Git. La evidencia se obtiene con datos sintéticos y fallos locales de testing; no constituye una prueba HTTP sobre una instalación desplegada. La revisión sistemática de logs y el ensayo explícito con marcadores de datos clínicos, credenciales, SQL y trazas corresponden al bloque 2, aún pendiente.
