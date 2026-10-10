# SCRUM-824 a SCRUM-827: contratos de entrada

Historia SCRUM-823 (A03), sprint 9. Fecha: 2026-10-09.

Este bloque inventaría las entradas y completa su validación; no representa el cierre de todas las subtareas de inyección y codificación de salida de A03.

## Canales y respuesta

Las 77 rutas API usan un `StrictFormRequest` concreto, incluidas consultas, aprobaciones, rechazos, eliminaciones y logout. La prueba `StrictInputValidationTest::test_every_api_action_declares_a_strict_form_request` recorre las rutas registradas y comprueba esta cobertura.

| Entrada | Contrato efectivo |
| --- | --- |
| Ruta | Los parámetros `{user}`, `{olderAdult}`, `{routineNote}`, `{rutina}`, `{schedule}`, `{assignment}`, `{inventoryItem}`, `{incident}`, `{vacationRequest}` y `{mobilityExercise}` exigen decimal positivo de 1–19 dígitos, sin cero inicial (`[1-9][0-9]{0,18}`). Luego se enlazan a modelos; sus Policies y `ResourceAccess` comprueban acceso. Sintaxis inválida y recursos ajenos/inexistentes producen 404 genérico. Los identificadores de filtros/cuerpo son enteros positivos. |
| Query | Sólo filtros de lectura declarados en el Request de la tabla. Las escrituras rechazan cualquier query con 422, incluso si repite un campo permitido en el cuerpo. |
| JSON | Objeto con campos explícitos. JSON inválido, escalares y listas no vacías en la raíz reciben 422 (`errors._body`). Se mantiene `[]` vacío por compatibilidad con comandos sin datos; los campos obligatorios siguen siendo exigidos. |
| Formulario | Mismas reglas y rechazo de campos extra que JSON. No hay campos de carga de archivos autorizados. `_method` sólo se admite como metadato de un POST cuando coincide con el método efectivo PUT/PATCH/DELETE; nunca pasa a los datos validados. |
| Authorization | `Bearer` del login en rutas privadas, validado por Sanctum, huella de seguridad, aprobación, rol, alcance y Policy. Sesiones web no reemplazan el token. |
| Content-Type / Accept | JSON se interpreta mediante `Content-Type: application/json`; formularios mediante el parser de Laravel. Cuerpos no vacíos de otros tipos reciben 422. Los errores de FormRequest siempre responden JSON 422, incluso sin `Accept: application/json`. No se usan encabezados para asignar propietarios ni roles. |
| Content-Length | El middleware existente limita a `app.max_request_bytes` (2 MiB por defecto) el tamaño declarado. La configuración del servidor también debe limitar el cuerpo real. |
| Origin / encabezados CORS | El middleware CORS compara el origen con la lista configurada y sólo concede permisos CORS a coincidencias exactas. Publica métodos y encabezados admitidos para el navegador; no sustituye autenticación/autorización. OPTIONS es transporte, sin operación de negocio. |
| X-HTTP-Method-Override | El middleware existente rechaza métodos/overrides no permitidos antes de ejecutar operaciones. |

Los campos no declarados se rechazan con **422**, también dentro de `medications.*`, `days`, `actividades` e `instructions`. No se aplican parcialmente datos válidos del mismo envío. Se conserva la defensa de listas permitidas en servicios/modelos. Dentro de rutas reconocidas, autenticación y autorización siguen ejecutándose antes de validar el cuerpo/query; 401/403/404 no se sustituyen por errores de entrada. Una ruta cuyo identificador no cumple el patrón no coincide con el router y devuelve 404.

Cambio de contrato respecto a A01: los campos sensibles antes descartados silenciosamente ahora generan 422. Por ejemplo, PUT `/api/me` con `role`, `is_approved`, `password`, `id`, propietarios o auditoría falla completo. Los clientes deben enviar sólo campos del formulario. El administrador puede cambiar `role` en su operación administrativa y `is_approved` en la actualización administrativa autorizada; no en registro/perfil público. Las aprobaciones se realizan con su comando sin campos.

## Tipos, longitudes, formatos, rangos y valores

Las reglas ejecutables completas están en `backend/app/Http/Requests`. `nullable` permite omitir o enviar null; no convierte objetos/listas en texto. Los campos de texto libre permanecen texto: esta etapa no elimina etiquetas ni sustituye la codificación contextual de salida.

| Request / operación | Campos y restricciones |
| --- | --- |
| `EmptyInputRequest` | Ningún campo de negocio. Rechaza datos en query/cuerpo para comandos sin parámetros y filtros no declarados en lecturas. |
| `AuthRequest::login` | `email`: texto, email, máximo 254; `password`: texto, máximo 1024. Ambos obligatorios. Rechaza `abilities` y otros campos de control de tokens. |
| `AuthRequest::register` | `name` texto obligatorio máximo 255; email obligatorio único máximo 254; contraseña obligatoria 12–1024, mayúsculas/minúsculas, letras, números y símbolos; `role` opcional familiar/profesional y sus alias cuidador; `privacy_consent` obligatorio aceptado. `location`/`phone` opcionales texto máximo 255; nacimiento según regla común. Aprobación, auditoría, versión/fecha de consentimiento provienen del servidor. |
| `AuthRequest::updateMe` | Nombre y email obligatorios; email único excluyendo usuario actual. Contacto/nacimiento como registro; `current_password` texto opcional máximo 1024; `new_password` fuerte y confirmada con `new_password_confirmation`, ambos máximo 1024. |
| `AdminUserRequest` | Nombre/email/contacto/nacimiento como registro; email único excluyendo modelo enlazado. `role` obligatorio admin/familiar/profesional/cuidador_familiar/cuidador_profesional. Contraseña fuerte obligatoria al crear y opcional al editar. Sólo al editar: `is_approved` booleano obligatorio. |
| `OlderAdultRequest` | Nombre obligatorio máximo 255; edad entero 0–130; nacimiento común; género femenino/masculino/otro; estado estable/atención/crítico/en observación (sin distinguir mayúsculas, con variantes sin acento). Habitación, cuidador familiar de compatibilidad, contacto de emergencia, teléfono y alergias texto opcional máximo 255. Historia clínica/notas máximo 10000. IDs de cuidadores positivos, existentes, aprobados y del rol correspondiente. |
| `OlderAdultRequest::medications` | Lista opcional máximo 100. Cada elemento: nombre obligatorio máximo 255; ID opcional positivo, sujeto a propiedad del adulto en servicio; presentación/dosis/horario máximo 255; unidad máximo 80; cantidad y mínimo de stock enteros 0–4294967295; vencimiento YYYY-MM-DD; notas máximo 2000; días lista máximo 7 de lunes/martes/miércoles/jueves/viernes/sábado/domingo, con variantes sin acento y sin distinguir mayúsculas. No acepta propietarios, auditoría ni `medication_id` inyectados. |
| `MedicationInventoryRequest` | Filtro `older_adult_id` positivo existente opcional. Crear/editar: nombre/presentación obligatorios máximo 255, unidad obligatoria máximo 80, cantidad/mínimo obligatorios enteros 0–4294967295, vencimiento opcional YYYY-MM-DD, `is_active` booleano opcional, dosis/horario opcionales máximo 255. Stock: acción obligatoria increase/decrease y cantidad obligatoria 1–4294967295. El servicio evita stock negativo y desbordamiento en la suma bajo bloqueo transaccional. |
| `MedicationAdministrationRequest` | Hora opcional HH:MM, notas opcionales texto máximo 10000. Identidad, adulto y tipo de administración se derivan en servidor. |
| `MobilityExerciseRequest` | Slug opcional único máximo 255, letras a-z/números separados por guiones simples. Título/enfoque/repeticiones obligatorios texto máximo 255; duración entera 1–1440; instrucciones lista obligatoria 1–20, cada texto obligatorio máximo 1000; precaución obligatoria máximo 2000; activo booleano opcional; orden entero opcional 0–65535. |
| `MobilityIndexRequest` | `active` opcional: 0, 1, true, false. |
| `StoreRoutineRequest` / `UpdateRoutineRequest` | Nombre obligatorio texto máximo 255, horario obligatorio HH:MM; actividades lista obligatoria 1–100, cada texto obligatorio máximo 255. Crear exige exactamente uno de adulto_mayor_id/older_adult_id, entero positivo y autorizado. Editar no admite cambiar adulto ni creador. |
| `CompleteRoutineActivityRequest` | Exactamente uno de actividad (texto máximo 255) o actividad_index (entero 0–99); el servicio verifica que exista en la rutina. |
| `RoutineIndexRequest` | Filtros adulto_mayor_id/older_adult_id opcionales, enteros positivos, sujetos a acceso al adulto. |
| `RoutineNoteRequest` | Listar: older_adult_id obligatorio positivo. Crear: mismo ID y contenido obligatorio texto máximo 5000. Editar: sólo contenido obligatorio máximo 5000. |
| `ProfessionalIncidentRequest` | Crear: older_adult_id obligatorio positivo, título obligatorio máximo 255; descripción opcional máximo 2000, severidad opcional baja/media/alta, fecha YYYY-MM-DD, hora HH:MM. Editar: descripción/severidad y estado abierto/en_progreso/resuelto/cerrado opcionales. Propietario y fecha no se cambian al editar. |
| `SaveCaregiverScheduleRequest` | Día entero obligatorio 0–6; inicio/fin HH:MM obligatorios, fin posterior a inicio; notas opcionales máximo 255. Sólo alta administrativa: user_id obligatorio positivo, profesional aprobado existente. |
| `ScheduleChangeRequest` | Inicio/fin obligatorios HH:MM y orden válido; notas opcionales máximo 255; mensaje obligatorio texto máximo 500. Estado de revisión se determina en servidor. |
| `ScheduleCalendarRequest` | Inicio/fin obligatorios YYYY-MM-DD, fin igual o posterior; diferencia máxima 366 días para acotar generación del calendario. |
| `VacationStoreRequest` | Inicio obligatorio YYYY-MM-DD no anterior a hoy; fin obligatorio YYYY-MM-DD igual o posterior; motivo obligatorio texto máximo 500. Estado/revisor/propietario no admitidos. |
| `DateFilterRequest` / `CareFilterRequest` | Fecha opcional YYYY-MM-DD; CareFilter también older_adult_id opcional entero positivo autorizado. |
| `MedicationStatisticsRequest` | Filtro opcional day/month/year. |

Reglas comunes: nacimiento YYYY-MM-DD entre 1800-01-01 y hoy; booleanos según Laravel (`true`, `false`, 0, 1, "0", "1"); números enteros admiten representación decimal de formularios. Límites de texto se expresan en caracteres. Colecciones son listas consecutivas desde índice 0. Las relaciones no confían en nombres como prueba de autorización.

## Inventario por ruta

GET incluye HEAD. En lecturas, la columna Request define los filtros permitidos en query; en escrituras define JSON/formulario. Los parámetros entre llaves pertenecen siempre a la ruta. El inventario de roles complementario se mantiene en [INVENTARIO_ENDPOINTS_ROLES.md](INVENTARIO_ENDPOINTS_ROLES.md).

| Método | Ruta | Request |
| --- | --- | --- |
| GET / HEAD | `/api/admin/dashboard-summary` | `EmptyInputRequest` |
| GET / HEAD | `/api/admin/family-caregivers` | `EmptyInputRequest` |
| POST | `/api/admin/incidents` | `ProfessionalIncidentRequest` |
| GET / HEAD | `/api/admin/medication-statistics` | `MedicationStatisticsRequest` |
| GET / HEAD | `/api/admin/medications/inventory` | `MedicationInventoryRequest` |
| POST | `/api/admin/medications/inventory` | `MedicationInventoryRequest` |
| PUT | `/api/admin/medications/inventory/{inventoryItem}` | `MedicationInventoryRequest` |
| DELETE | `/api/admin/medications/inventory/{inventoryItem}` | `EmptyInputRequest` |
| PATCH | `/api/admin/medications/inventory/{inventoryItem}/stock` | `MedicationInventoryRequest` |
| GET / HEAD | `/api/admin/mobility-exercises` | `MobilityIndexRequest` |
| POST | `/api/admin/mobility-exercises` | `MobilityExerciseRequest` |
| GET / HEAD | `/api/admin/mobility-exercises/{mobilityExercise}` | `EmptyInputRequest` |
| PUT | `/api/admin/mobility-exercises/{mobilityExercise}` | `MobilityExerciseRequest` |
| DELETE | `/api/admin/mobility-exercises/{mobilityExercise}` | `EmptyInputRequest` |
| GET / HEAD | `/api/admin/older-adults` | `EmptyInputRequest` |
| POST | `/api/admin/older-adults` | `OlderAdultRequest` |
| GET / HEAD | `/api/admin/older-adults/{olderAdult}` | `EmptyInputRequest` |
| PUT | `/api/admin/older-adults/{olderAdult}` | `OlderAdultRequest` |
| DELETE | `/api/admin/older-adults/{olderAdult}` | `EmptyInputRequest` |
| GET / HEAD | `/api/admin/professional-caregivers` | `EmptyInputRequest` |
| GET / HEAD | `/api/admin/schedules` | `EmptyInputRequest` |
| POST | `/api/admin/schedules` | `SaveCaregiverScheduleRequest` |
| GET / HEAD | `/api/admin/schedules/calendar` | `ScheduleCalendarRequest` |
| DELETE | `/api/admin/schedules/{schedule}` | `EmptyInputRequest` |
| PATCH | `/api/admin/schedules/{schedule}/change-request/approve` | `EmptyInputRequest` |
| PATCH | `/api/admin/schedules/{schedule}/change-request/reject` | `EmptyInputRequest` |
| GET / HEAD | `/api/admin/users` | `EmptyInputRequest` |
| POST | `/api/admin/users` | `AdminUserRequest` |
| GET / HEAD | `/api/admin/users/{user}` | `EmptyInputRequest` |
| PUT | `/api/admin/users/{user}` | `AdminUserRequest` |
| DELETE | `/api/admin/users/{user}` | `EmptyInputRequest` |
| PATCH | `/api/admin/users/{user}/approve` | `EmptyInputRequest` |
| DELETE | `/api/admin/users/{user}/reject` | `EmptyInputRequest` |
| GET / HEAD | `/api/admin/vacation-requests` | `EmptyInputRequest` |
| PATCH | `/api/admin/vacation-requests/{vacationRequest}/approve` | `EmptyInputRequest` |
| PATCH | `/api/admin/vacation-requests/{vacationRequest}/reject` | `EmptyInputRequest` |
| GET / HEAD | `/api/family/incidents` | `CareFilterRequest` |
| GET / HEAD | `/api/family/older-adults` | `EmptyInputRequest` |
| GET / HEAD | `/api/family/older-adults/{olderAdult}` | `EmptyInputRequest` |
| GET / HEAD | `/api/family/older-adults/{olderAdult}/incidents` | `DateFilterRequest` |
| GET / HEAD | `/api/family/overview` | `EmptyInputRequest` |
| GET / HEAD | `/api/family/routine` | `CareFilterRequest` |
| GET / HEAD | `/api/family/routines` | `CareFilterRequest` |
| GET / HEAD | `/api/incidents` | `DateFilterRequest` |
| GET / HEAD | `/api/incidents/today` | `EmptyInputRequest` |
| POST | `/api/login` | `AuthRequest` |
| POST | `/api/logout` | `EmptyInputRequest` |
| GET / HEAD | `/api/me` | `EmptyInputRequest` |
| PUT | `/api/me` | `AuthRequest` |
| POST | `/api/medications/{assignment}/taken` | `MedicationAdministrationRequest` |
| GET / HEAD | `/api/mobility-exercises` | `MobilityIndexRequest` |
| GET / HEAD | `/api/mobility-exercises/{mobilityExercise}` | `EmptyInputRequest` |
| GET / HEAD | `/api/ping` | `EmptyInputRequest` |
| POST | `/api/professional/incidents` | `ProfessionalIncidentRequest` |
| PATCH | `/api/professional/incidents/{incident}` | `ProfessionalIncidentRequest` |
| GET / HEAD | `/api/professional/older-adults` | `EmptyInputRequest` |
| GET / HEAD | `/api/professional/older-adults/{olderAdult}` | `EmptyInputRequest` |
| GET / HEAD | `/api/professional/overview` | `EmptyInputRequest` |
| GET / HEAD | `/api/professional/reminders` | `EmptyInputRequest` |
| GET / HEAD | `/api/professional/routine-notes` | `RoutineNoteRequest` |
| POST | `/api/professional/routine-notes` | `RoutineNoteRequest` |
| GET / HEAD | `/api/professional/routine-notes/{routineNote}` | `EmptyInputRequest` |
| PUT | `/api/professional/routine-notes/{routineNote}` | `RoutineNoteRequest` |
| DELETE | `/api/professional/routine-notes/{routineNote}` | `EmptyInputRequest` |
| GET / HEAD | `/api/professional/routines` | `CareFilterRequest` |
| GET / HEAD | `/api/professional/schedules` | `EmptyInputRequest` |
| GET / HEAD | `/api/professional/vacation-requests` | `EmptyInputRequest` |
| POST | `/api/professional/vacation-requests` | `VacationStoreRequest` |
| POST | `/api/register` | `AuthRequest` |
| GET / HEAD | `/api/rutinas` | `RoutineIndexRequest` |
| POST | `/api/rutinas` | `StoreRoutineRequest` |
| PUT | `/api/rutinas/{rutina}` | `UpdateRoutineRequest` |
| DELETE | `/api/rutinas/{rutina}` | `EmptyInputRequest` |
| PATCH | `/api/rutinas/{rutina}/completar` | `CompleteRoutineActivityRequest` |
| POST | `/api/schedules` | `SaveCaregiverScheduleRequest` |
| PUT | `/api/schedules/{schedule}` | `SaveCaregiverScheduleRequest` |
| POST | `/api/schedules/{schedule}/change-request` | `ScheduleChangeRequest` |

Las cuatro rutas de infraestructura ajenas a /api son `/`, `/up`, `/sanctum/csrf-cookie` y `/storage/{path}`. Las tres primeras no reciben datos de negocio; storage usa el parámetro de ruta `path` y query `signature`/`expires`, validados mediante firma relativa y expiración por Laravel. No hay emisión de URLs de storage en los servicios actuales. OPTIONS se atiende en CORS antes de las acciones.

## Verificación

- Suite completa: **368 pruebas, 6759 aserciones, sin errores**. Comando desde backend: `php vendor/phpunit/phpunit/phpunit --log-junit storage/logs/a03-validation.xml`. Evidencia local: `storage/logs/a03-validation.txt` y XML JUnit; no se incluyen credenciales en esos resultados.
- 43 casos nuevos en `StrictInputValidationTest`: cobertura de las 77 rutas/contratos y sus parámetros, campos extra de JSON/formularios, campos anidados, rechazo atómico, JSON inválido, tipo de cuerpo, separación query/cuerpo, comandos sin datos, tipos/longitudes/formatos/rangos/listas, valores permitidos, límites válidos, overflow de stock y precedencia de permisos. Pruebas existentes de A01 actualizadas al contrato 422, con reintentos limpios y verificaciones de propiedad/credenciales intactas.
- Laravel Pint: comprobación satisfactoria de Requests, controladores, servicio de inventario, rutas y pruebas del bloque.
- Entorno realmente ejecutado: PHP **8.2.12**, Laravel **11.51.0**, PHPUnit **11.5.55**, SQLite en memoria. El manifiesto declara PHP ^8.3, Laravel ^13 y PHPUnit ^12; esta ejecución no certifica esas versiones. No se instalaron dependencias ni se ejecutaron migraciones contra la base de datos de la aplicación.
- No se realizaron acciones de Git.
