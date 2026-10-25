# SCRUM-1018: verificación de errores del servidor en la API

## Bloques para commits manuales

| Bloque | Subtareas | Entrega |
|---|---|---|
| 1 | SCRUM-1020 Provocar respuestas 500; SCRUM-1021 Inspeccionar las respuestas de la API | Pruebas automatizadas de fallos y contrato HTTP. |
| 2 | SCRUM-1022 Revisar los registros del sistema; SCRUM-1023 Confirmar que no se expongan datos clínicos, tokens, SQL ni trazas internas | Inspección de archivos reales de testing, marcadores de privacidad y corrección del contexto del log operativo. |

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
- Cuerpo JSON conforme al contrato común: `code=internal_server_error`, el mensaje `Error interno del servidor.` y `errors={}`. El contrato completo, incluidos los errores 4xx, está en `CONTRATO_ERRORES_API_SPRINT10.md`.
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

La evidencia del bloque 1 se obtiene con datos sintéticos y fallos locales de testing; no constituye una prueba HTTP sobre una instalación desplegada. La revisión adicional de logs y marcadores se describe abajo.

## Inspección de registros (SCRUM-1022)

Mensaje sugerido para el segundo commit: `SCRUM-1022 SCRUM-1023: verificar privacidad y correlacionar logs de errores API`.

`backend/tests/NonFunctional/Security/ApiErrorLogPrivacyTest.php` genera archivos físicos en un directorio único bajo `storage/framework/testing/api-error-privacy-<UUID>`. Redirige allí el canal operativo `single` y el canal diario `security_audit`, manteniendo sus procesadores de protección. Utiliza JSON para inspeccionar el archivo operativo y conserva la configuración de firma/sanitización del canal de auditoría. Desactiva el colector externo durante el ensayo. Cierra los handlers y elimina únicamente su directorio de prueba al finalizar, incluso ante un fallo de aserción.

La inspección comprueba positivamente que existan eventos, en lugar de aceptar un archivo vacío como evidencia de privacidad:

- Una excepción reportable de aplicación, SQL, almacenamiento o clase anónima genera un único registro operativo `API request failed.` con nivel `ERROR`.
- Su contexto contiene únicamente `exception_type` y `correlation_id`; el UUID coincide con la cabecera de la respuesta.
- Cada fallo probado genera el evento firmado `infrastructure.application`; los errores SQL/almacenamiento generan además su clasificación correspondiente, sin duplicados.
- Cada línea de auditoría es JSON válido, tiene nivel `ERROR`, resultado `failure`, el mismo UUID y una firma verificable por `AuditRecordIntegrity`.
- Las respuestas HTTP explícitas, `HttpResponseException` y respuestas directas no tienen por qué ser reportadas como excepciones por Laravel. En esos casos la prueba exige el evento de auditoría, sin inventar un registro operativo de excepción.

Se corrigió el reporte operativo de `bootstrap/app.php`: ahora incluye el UUID del servidor. Además se utiliza el tipo padre de una excepción anónima, porque PHP incluye archivo y línea internos en el nombre de esa clase. Se conserva el mensaje genérico y no se añaden objetos de excepción, mensajes originales, cuerpos ni cabeceras de solicitud al contexto. Las pruebas existentes con mocks se actualizaron para exigir también la correlación.

## Ausencia de información sensible (SCRUM-1023)

El ensayo envía exclusivamente marcadores ficticios distinguibles: nombre de paciente, diagnóstico, token Bearer, contraseña, SQL, indicador SQLSTATE, ruta interna, traza y una línea que pretende simular otro evento. Coloca esos marcadores en el cuerpo/cabeceras de la solicitud y en mensajes de excepción, bindings SQL o cuerpos de respuestas fallidas.

Se prueban siete variantes: aplicación, consulta, almacenamiento, excepción anónima, HTTP 503, `HttpResponseException` y respuesta directa. Cada una se ejecuta con debug activado/desactivado y Accept JSON/HTML: **28 casos**. Para cada caso se inspeccionan conjuntamente cuerpo y cabeceras de respuesta, archivo operativo y archivo de auditoría. Ningún marcador puede sobrevivir en esas superficies; tampoco se permiten `@anonymous` ni el nombre del archivo de prueba como ruta interna. El cuerpo debe seguir siendo un error genérico con la estructura común y el UUID enviado por el cliente debe reemplazarse por uno generado por el servidor.

La validación se centra en los caminos de error API probados. No demuestra que todo mensaje arbitrario de cualquier biblioteca, endpoint no ensayado o registro histórico carezca de información sensible. Los archivos históricos de la aplicación y los registros de una instalación desplegada no se leyeron, modificaron ni depuraron en este bloque.

## Ejecución y evidencia del bloque 2

Desde `backend`:

```powershell
php vendor/phpunit/phpunit/phpunit --filter ApiErrorLogPrivacyTest
```

Resultado local específico: **28 pruebas y 848 aserciones aprobadas**.

Regresión conjunta de los dos bloques, protección de credenciales y auditoría:

```powershell
php vendor/phpunit/phpunit/phpunit --filter 'ApiErrorLogPrivacyTest|ApiServerErrorResponseTest|InjectionAndErrorDisclosureTest|RequestCorrelationIdTest|SecurityHeadersAndCorsTest|IncidentPersistenceTest|AuthenticationSecretProtectionTest|OutboundSecuritySignalsTest|SecurityAuditChannelTest|SecurityLogInjectionTest'
```

Resultado local: **132 pruebas y 1806 aserciones aprobadas**, con PHP 8.2.12 y PHPUnit 11.5.55. Se ejecutaron fuera del sandbox por las restricciones de lectura/procesos de PHP ya identificadas. Sigue pendiente repetir en el runtime declarado de PHP ^8.3/PHPUnit ^12 y, si se requiere evidencia de despliegue, sobre el ambiente de prueba desplegado. No se ejecutaron operaciones de Git ni se provocaron fallos en la aplicación en ejecución.
