# SCRUM-828 a SCRUM-831: consultas y contexto de salida

Historia SCRUM-823 (A03), sprint 9. Fecha: 2026-10-09. Bloque de cuatro subtareas, sin acciones de Git.

## SCRUM-828: revisión de consultas raw

Se revisaron los usos de `whereRaw`, `orderByRaw`, consultas del builder y operaciones de selección/ordenamiento en `backend/app`, `backend/routes` y `backend/database`. No se encontraron concatenaciones de entradas del cliente en SQL ejecutable. Las expresiones raw que permanecen son:

| Archivo | Expresión | Valores y motivo |
| --- | --- | --- |
| `app/Services/OlderAdultService.php` | `LOWER(name) = ?` | El nombre familiar de compatibilidad se envía como binding separado; no se concatena ni se escapa manualmente para SQL. La asignación encontrada sigue sujeta a rol y aprobación; el nombre no sustituye Policies. |
| `database/migrations/2026_05_01_000001_add_older_adult_to_incidents_table.php` | `LOWER(full_name) = ?` | Nombre histórico enlazado como parámetro. Migración existente revisada, sin modificación ni ejecución contra la BD de la aplicación. |
| `app/Services/VacationRequestService.php` | `CASE WHEN status = ? THEN 0 ELSE 1 END` | Se cambió el literal de estado a binding `pending`. Los números 0/1 y la expresión son constantes del programa. Se conserva el orden: pendientes primero, después creación descendente. |
| `app/Services/IncidentListingService.php` | `incident_time IS NULL` | Orden fijo para incidentes sin hora; no recibe SQL del cliente. |
| `app/Services/CaregiverAccessService.php` | `incident_time IS NULL` | Misma expresión fija en listados de cuidadores. |
| `app/Services/ScheduleCalendarService.php` | `incident_time IS NULL` | Misma expresión fija en eventos de calendario. |
| `app/Services/IncidentListingService.php` / `RoutineService.php` | `1 = 0` | Denegación por defecto constante para colecciones sin acceso. No contiene datos ni identificadores dinámicos. |

Los demás valores llegan a `where`, `whereIn`, `whereDate`, `whereBetween`, relaciones o escrituras Eloquent con bindings del framework. No se introducen funciones de escape SQL, interpolaciones de nombres de columnas ni SQL construido a partir de filtros.

## SCRUM-829: listas permitidas para consultas

Los identificadores SQL no se parametrizan como valores: se eligen exclusivamente en código. La API no expone selección libre de columnas, expresiones, orden o dirección. `StrictFormRequest` rechaza claves `columns`, `sort`, `order`, `direction`, `where` y cualquier otro campo no declarado con 422. No se añadió una interfaz nueva de ordenamiento dinámico.

| Selección | Lista permitida / decisión fija |
| --- | --- |
| Estadísticas | `MedicationStatisticsService::FILTERS`: day, month, year, compartida con `MedicationStatisticsRequest`. El servicio también rechaza valores ajenos a la lista antes de consultar, evitando que un llamador interno convierta un filtro desconocido en day silenciosamente. |
| Cuidadores administrativos | `AdminUserService::CAREGIVER_ROLES`: familiar, profesional. El servicio rechaza otros valores. Los dos endpoints correspondientes eligen el rol en servidor. |
| Otros filtros HTTP | Fechas con formato, IDs enteros positivos y acceso comprobado; active limitado a 0/1/true/false. Contratos completos en [INVENTARIO_ENTRADAS_A03.md](INVENTARIO_ENTRADAS_A03.md). |
| Proyecciones de usuarios | Listas literales de columnas en `AdminUserService`, sin contraseña, token ni campos seleccionados por cliente. |
| Orden de usuarios/adultos | created_at descendente, name o full_name, fijados en sus servicios/controladores. |
| Orden de rutinas/notas | horario/nombre; note_date/updated_at descendentes. |
| Orden de inventario/estadísticas | older_adult_id/medication_id; acquired_at y clasificación de unidades calculadas. |
| Orden de turnos/calendario | day_of_week/start_time; campos fijos de fecha/hora y las expresiones constantes inventariadas. |
| Orden de movilidad/vacaciones/incidentes | sort_order/title; prioridad de pending y created_at; fecha/hora/creación de incidentes. |

## SCRUM-830: reemplazo de innerHTML inseguro

Se sustituyeron las interpolaciones de datos e identificadores en los siguientes flujos por `createElement`, `textContent`, `replaceChildren`, `.dataset` y propiedades de formulario:

- `admin/users.js`: filas, mensajes, botones y navegación de usuarios.
- `admin/adultos-mayores.js`: filas, mensajes y botones de adultos.
- `admin/new-user.js`: tarjetas y acciones de solicitudes de registro.
- `admin/shifts-view.js`: selectores, turnos, vacaciones y solicitudes de cambio. Los callbacks se reciben explícitamente desde `shifts.js`, conservando las operaciones.
- `admin/shifts-calendar-view.js`: tarjetas de turnos de mes, semana y día; estados vacío/cargando/error.
- `cuidador-profesional/routines-view.js`: selector de adulto, rutinas personalizadas, actividades, notas, acciones y estados vacíos.
- `cuidador-profesional/shift.js`: turnos y formularios para solicitar cambios.
- `admin/older-adult-form.js`: tarjetas de medicamentos, campos de texto/textarea y casillas de días.
- `admin/dashboard-medicines-stats.js`: opciones de selección de adultos.
- `admin/medication-stats-view.js`: barras, etiquetas y altura del gráfico.

El helper `CuidadoUi.element` crea nodos y texto; no interpreta cadenas como HTML y no ofrece atributos arbitrarios ni manejadores inline. Sus nombres de etiqueta/clases provienen del código o de decisiones de presentación conocidas. Los hijos Node son nodos construidos por la aplicación.

Se revisaron también los usos de HTML restantes. Se conservan plantillas estáticas de popups/carga/iconos, estados constantes y plantillas que ya escapan los datos con `CuidadoUi.escapeHtml`, directamente o a través de FamilyCare/ProfessionalCare. Entre ellas están resúmenes de dashboard, listas clínicas familiares, incidentes, rutinas administrativas, ranking/inventario y eventos del calendario. Sus clases e iconos se eligen desde constantes/mapas de presentación; los textos y atributos de datos variables se escapan para HTML. La fecha formateada y los conteos son valores derivados, sin markup del cliente. Los botones temporales de guardado sólo restauran su propio contenido original.

El centro de notificaciones actual recibe destinos relativos fijos de `care-notifications-core.js`; su escape HTML no constituye validación de protocolos. El endurecimiento general de URLs corresponde a SCRUM-832. Este bloque no declara eliminados todos los usos de `innerHTML` ni cerrada toda la historia A03.

## SCRUM-831: tratamiento según contexto

| Contexto | Tratamiento |
| --- | --- |
| Texto DOM | `textContent` / nodos de texto con el valor original. No se aplica `escapeHtml` antes, evitando mostrar entidades artificiales o escapar dos veces. |
| Formulario | `.value` para nombre, dosis, horario, notas y campos del turno; `.checked`, `.required`, `.disabled`, `.hidden` para estados. Una comilla no puede cerrar un atributo porque no se analiza HTML. |
| Atributos de datos | `.dataset.id`, `.dataset.noteId`, `.dataset.shiftId`, etc. El valor permanece un dato literal. Los eventos usan `addEventListener`. |
| HTML conservado | `escapeHtml` cubre ampersand, ángulos y ambas comillas en texto/atributos entrecomillados. No se usa como validador SQL, CSS o URL. |
| Componente URL | `URLSearchParams` para navegación de usuarios/adultos; `encodeURIComponent` para IDs incorporados a rutas API y al enlace profesional de rutinas. Los destinos base se fijan en el programa. |
| CSS del gráfico | Conversión numérica, rechazo de no finitos y negativos, altura acotada 0–100 y asignación a `.style.height`. No se concatena una cadena del cliente como CSS. |
| Selector DOM | La edición de notas busca tarjetas con un selector fijo y compara `dataset.noteId` por igualdad; no interpola el identificador en sintaxis CSS. |
| Persistencia/JSON | Se preserva texto original validado, incluidos apóstrofos y ángulos; se enlaza como dato SQL y se serializa mediante las respuestas JSON del framework. La protección se aplica al consumirlo en su contexto de salida. No se elimina HTML de texto clínico a ciegas. |

No hay una necesidad de HTML enriquecido en los flujos migrados; por ello se muestran valores como texto en lugar de incorporar un sanitizador o añadir dependencias.

## Validación

- Backend: **404 pruebas, 6892 aserciones, sin fallos**. 36 casos nuevos en `ParameterizedQueriesTest`: binding real de nombres con metacaracteres, binding de prioridad de vacaciones, rechazo HTTP de identificadores SQL libres y validación interna de listas permitidas. Se mantienen las pruebas de autorización, propiedad y contrato de entrada.
- Frontend: **51 archivos, 136 pruebas, sin fallos**. 13 casos nuevos en `safe-output-context.test.js` y `safe-admin-lists.test.js`: valores literales sin doble escape, formularios, identificadores con comillas, callbacks, notas/rutinas, tres vistas de calendario, URL de navegación, CSS acotado, búsqueda de notas sin interpolar selectores y eventos del formulario profesional. La regresión existente comprueba listados, creación/edición, aprobaciones y formularios.
- Comandos desde backend/frontend respectivamente: `php vendor/phpunit/phpunit/phpunit --log-junit storage/logs/a03-queries.xml` y `npm.cmd test -- --reporter=dot`. Resultados locales en `backend/storage/logs/a03-queries.txt`, XML y `a03-dom.txt`.
- Laravel Pint: verificación de servicios, Request y prueba del bloque.
- Entorno ejecutado: PHP 8.2.12, Laravel 11.51.0, PHPUnit 11.5.55, SQLite en memoria; Node 24.14.1, Vitest 3.2.7 y jsdom 26.1.0. Los manifiestos declaran versiones superiores de algunas dependencias; estas pruebas certifican el entorno instalado. No se instalaron ni actualizaron dependencias, no se ejecutaron migraciones contra la BD de la aplicación y no se realizaron acciones de Git.
