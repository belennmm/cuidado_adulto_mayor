# SCRUM-1024: separación de responsabilidades

La captura de la historia titula el trabajo como reutilización de factories y helpers de pruebas, pero sus subtareas describen separación de servicios. Este documento sigue las subtareas visibles; reutiliza las factories/helpers existentes al verificar el refactor.

| Bloque | Subtareas | Alcance |
|---|---|---|
| 1 | SCRUM-1025 Identificar servicios con varias responsabilidades; SCRUM-1026 Extraer consultas complejas | Inventario de responsabilidades y extracción de consultas de estadísticas y calendario. |
| 2 | SCRUM-1027 Separar validaciones y autorizaciones restantes; SCRUM-1034 Extraer la transformación de respuestas | Pendiente. |

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

No se ejecutaron operaciones de Git, migraciones sobre la base de la aplicación ni cambios de frontend. SCRUM-1027 y SCRUM-1034 permanecen pendientes para el siguiente bloque; las validaciones y transformaciones que aún aparecen en los servicios se conservan deliberadamente hasta entonces.
