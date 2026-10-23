# SCRUM-1024: separación de responsabilidades

La captura de la historia titula el trabajo como reutilización de factories y helpers de pruebas, pero sus subtareas describen separación de servicios. Este documento sigue las subtareas visibles; reutiliza las factories/helpers existentes al verificar el refactor.

| Bloque | Subtareas | Alcance |
|---|---|---|
| 1 | SCRUM-1025 Identificar servicios con varias responsabilidades; SCRUM-1026 Extraer consultas complejas | Inventario de responsabilidades y extracción de consultas de estadísticas y calendario. |
| 2 | SCRUM-1027 Separar validaciones y autorizaciones restantes; SCRUM-1034 Extraer la transformación de respuestas | Validación del filtro y acceso a incidentes separados; transformadores de estadísticas/calendario sin consultas propias. |

Los commits y todas las operaciones de Git quedan a cargo del usuario. Mensaje sugerido para el bloque 1: `SCRUM-1025 SCRUM-1026: identificar responsabilidades y extraer consultas de servicios`.

## Inventario de responsabilidades (SCRUM-1025)

| Servicio | Responsabilidades observadas | Decisión para este bloque |
|---|---|---|
| `MedicationStatisticsService` | Valida el filtro; calcula períodos; consulta adquisiciones/inventario; agrupa cantidades; construye gráficos, etiquetas y respuesta paginada. | Extraer las consultas de adquisiciones e inventario. Conservar por ahora validación, cálculo y presentación. |
| `ScheduleCalendarService` | Consulta horarios/adultos/vacaciones/incidentes; expande días de turnos; calcula estados y notas; transforma y pagina turnos/eventos. | Extraer selección y agrupación de datos persistidos. Mantener las reglas de turnos y la presentación. |
| `IncidentListingService` | Verifica rol/aprobación; limita adultos visibles; filtra/ordena incidentes; pagina. | Candidato a separar autorización de consulta en el bloque siguiente. No se modifica aquí. |
| `OlderAdultService` | Filtra/normaliza entradas; valida cuidadores; coordina transacciones; sincroniza medicamentos; carga relaciones. | Candidato de validación de asignaciones. Conservar sus transacciones y reglas en este bloque. |
| `CareDataService` | Consulta administraciones; aplica reglas de días/horarios; transforma datos de adultos/cuidadores y rutinas. | Candidato a separar lectura y transformación. Se documenta sin ampliar este bloque. |

La selección se basa en responsabilidades distintas dentro de los métodos, no sólo en longitud de archivo. Se priorizan estadísticas y calendario porque ambos mezclan lecturas de varias entidades con composición de respuestas y cuentan con regresiones HTTP existentes.

## Extracción de consultas (SCRUM-1026)

Las clases de `app/Queries` encapsulan lecturas Eloquent; Laravel las inyecta en los servicios mediante su contenedor, sin bindings adicionales. No ejecutan escrituras ni construyen JSON HTTP.

- `MedicationStatisticsQuery`: adquisiciones de un período con su medicamento precargado y consulta de inventario con medicamento/adulto y conteo de administraciones. El inventario devuelve un Builder para conservar la paginación SQL del servicio.
- `ScheduleCalendarQuery`: horarios con cuidador, adultos agrupados por cuidador, vacaciones aprobadas para calcular turnos y vacaciones/incidentes del intervalo para los eventos. Los métodos de vacaciones comparten el criterio de solapamiento.

Se conservan filtros, selección de columnas, eager loading y órdenes existentes. En particular, sólo las vacaciones aprobadas afectan estados de turnos; los eventos muestran todos los estados que se solapan con el intervalo. El inventario incluye asignaciones inactivas y sin adulto, como antes. No se agrega un filtro nuevo ni se cambia el algoritmo de estadísticas, la validación, las policies o las respuestas.

Estas consultas administrativas no constituyen una capa de autorización y no deben conectarse directamente a rutas públicas o de cuidadores. Los controladores/middleware/policies actuales siguen controlando su acceso. Las clases extraídas tampoco prometen reducir el número de consultas: el objetivo es separar responsabilidades conservando comportamiento.

## Verificación del primer bloque

`tests/Feature/ExtractedServiceQueriesTest.php` usa las factories existentes de medicamentos e inventario y el trait `CreatesCareTestData` para usuarios/adultos. Sólo agrega un helper local de vacaciones porque no existe una factory correspondiente y el fixture se repite en varios escenarios del mismo test.

Sus cinco pruebas verifican:

- Adquisiciones en ambos límites del período, exclusión de fechas contiguas y precarga del nombre del medicamento.
- Inventario paginado con filas inactivas y sin adulto, relaciones precargadas y conteos de administraciones.
- Agrupación de adultos sólo para cuidadores seleccionados, selección vacía y orden de horarios.
- Solapamiento de vacaciones en ambos límites; diferencia entre aprobadas para turnos y todos los estados para eventos.
- Inclusión de incidentes en ambos límites, orden de horas nulas y precarga del adulto/reportante.

Antes del refactor se ejecutó la referencia HTTP de estadísticas, calendario, horarios, paginación y autorización: **33 pruebas y 681 aserciones aprobadas**. Después se ejecutó esa cobertura junto con las cinco pruebas nuevas y las comprobaciones de consultas parametrizadas:

```powershell
# Desde backend
php vendor/phpunit/phpunit/phpunit --filter 'ExtractedServiceQueriesTest|AdminMedicationStatisticsTest|CaregiverScheduleCalendarScrumTest|CaregiverScheduleEndpointsTest|BoundedSensitiveListsTest|AdminAuthorizationTest|ParameterizedQueriesTest'
```

Resultado: **74 pruebas y 852 aserciones aprobadas**. También pasó la revisión de sintaxis de las dos consultas, ambos servicios y la prueba nueva. Runtime local: PHP 8.2.12 / PHPUnit 11.5.55 con SQLite en memoria; el proyecto declara PHP ^8.3 y PHPUnit ^12, por lo que queda pendiente verificar con esas dependencias y con PostgreSQL en el entorno correspondiente. Las ejecuciones PHP requirieron salir del sandbox por las restricciones locales ya identificadas.

No se ejecutaron operaciones de Git, migraciones sobre la base de la aplicación ni cambios de frontend. La separación posterior de validaciones y presentación se describe en el bloque 2.

## Validación y autorización separadas (SCRUM-1027)

Mensaje sugerido para el último commit: `SCRUM-1027 SCRUM-1034: separar validacion, autorizacion y transformacion de respuestas`.

`Validation/MedicationStatisticsFilter` concentra los valores `day`, `month`, `year` y su rechazo fuera de esa lista. El FormRequest reutiliza la constante y el servicio valida antes de consultar. Se conserva el mensaje de error y la validación al invocar el servicio directamente. `MedicationStatisticsService::FILTERS` sigue disponible como alias de compatibilidad. La regla HTTP existente para valores nullable no se cambia.

`Authorization/IncidentListingAccess` verifica rol reconocido y aprobación antes de cualquier lectura. Devuelve el rol normalizado, conserva los mensajes y HTTP 403 del acceso directo al servicio, incluidos administradores pendientes, y admite los alias históricos. Las policies y el middleware HTTP existentes siguen activos.

`Queries/IncidentListingQuery` conserva la selección por asignaciones de familiares/profesionales, ausencia de resultados sin adultos asignados, fecha, relaciones precargadas y orden. `IncidentListingService` coordina autorización, consulta y paginación. La consulta recibe el rol ya comprobado por el servicio; es una dependencia interna, no una API de autorización para llamadores externos.

Este bloque se limita a estadísticas, calendario y listado de incidentes dentro del alcance elegido. `OlderAdultService` y `CareDataService` continúan como candidatos del inventario; sus transacciones y reglas de negocio no forman parte de esta entrega.

## Transformación de respuestas (SCRUM-1034)

`Transformers/MedicationStatisticsTransformer` recibe adquisiciones ya cargadas, la página de inventario y el período. Agrupa cantidades y construye líder, etiquetas, gráficos, metadatos y filas de inventario, conservando nombres y tipos de campos. El cálculo del período y la ejecución de consultas permanecen en el servicio y las clases de consulta.

`Transformers/ScheduleCalendarTransformer` recibe turnos, vacaciones e incidentes cargados. Transforma modelos en eventos, presenta turnos/horas y construye páginas independientes de turnos y eventos. La expansión de días y las reglas de estado/notas siguen en `ScheduleCalendarService`.

Ambos transformadores reciben las relaciones necesarias ya precargadas. No contienen consultas Eloquent, autorización ni validación de entrada. Las pruebas comprueban que sus llamadas con datos cargados no generen SQL; no deben recibir modelos incompletos que disparen lazy loading.

## Verificación del último bloque

Referencia previa al cambio: **88 pruebas y 894 aserciones aprobadas**. `SeparatedServiceResponsibilitiesTest` agrega 13 casos con las factories y `CreatesCareTestData` existentes:

- Filtros inválidos rechazados con el mismo error y sin consultas.
- Roles desconocidos y cuentas pendientes rechazados antes de consultar.
- Aislamiento para roles familiares/profesionales canónicos y heredados, con/sin paginación, otra fecha y cuidadores sin asignación.
- Presentación de estadísticas sin SQL, conservando gráficos, inventario sin adulto, tipos y metadatos.
- Presentación de calendario sin SQL, con alternativas para relaciones nulas y páginas independientes.

Resultado específico: **13 pruebas y 60 aserciones aprobadas**. También pasó la revisión de sintaxis de los diez archivos PHP nuevos/modificados.

Regresión final, desde `backend`:

```powershell
php vendor/phpunit/phpunit/phpunit --filter 'SeparatedServiceResponsibilitiesTest|ExtractedServiceQueriesTest|AdminMedicationStatisticsTest|CaregiverScheduleCalendarScrumTest|CaregiverScheduleEndpointsTest|BoundedSensitiveListsTest|AdminAuthorizationTest|ParameterizedQueriesTest|IncidentEndpointsTest|BackupIncidentBaselineTest|BackendAccessControlTest|FamilyCaregiverDataIsolationTest|ApiServerErrorResponseTest|RecoveredCareDataTest'
```

Resultado: **169 pruebas y 2401 aserciones aprobadas**. La selección incluye los servicios del bloque anterior, endpoints, permisos, errores 500 y datos recuperados que dependen de los constructores modificados. La suite emitió el aviso genérico `security_audit_capacity_limited` al alcanzar el presupuesto de eventos de auditoría; no hubo errores ni fallos de pruebas.

Runtime local: PHP 8.2.12/PHPUnit 11.5.55 con SQLite en memoria. Queda pendiente repetir con PHP ^8.3/PHPUnit ^12 y PostgreSQL en el entorno correspondiente. No se ejecutaron operaciones de Git; todas siguen a cargo del usuario.
