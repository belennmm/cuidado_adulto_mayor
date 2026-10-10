# SCRUM-781: inventario de endpoints y roles del backend

Fecha: 2026-10-09. Historia SCRUM-779 (A01), sprint 9.

Cobertura: **81 rutas HTTP**, incluidas **77 rutas API** (3 públicas y 74 privadas).

Inventario obtenido de `php artisan route:list --json`, contrastado con `routes/api.php`, `routes/web.php`, `bootstrap/app.php`, Requests, servicios y `RutinaPolicy`. Cada fila representa una combinación de ruta y métodos registrados; GET incluye HEAD. Las rutas API llevan el prefijo `/api`.

## Roles y reglas de acceso

- **A**: administrador (`admin`). El middleware administrativo mantiene la comprobación exacta de este valor. No obtiene acceso implícito a las rutas de cuidadores.
- **P**: cuidador profesional (`profesional` o `cuidador_profesional`), aprobado.
- **F**: cuidador familiar (`familiar` o `cuidador_familiar`), aprobado.
- **Sesión**: cualquier usuario autenticado puede consultar/editar su propio perfil y cerrar su sesión, incluso si perdió la aprobación. Estas operaciones no conceden acceso a información de cuidado.
- Un rol desconocido o vacío no concede acceso a recursos de cuidado, aunque el usuario esté aprobado.
- En rutas de cuidado, un cuidador pendiente recibe 403, incluso con un token emitido anteriormente. La aprobación se comprueba en cada solicitud.
- Sin autenticación válida, las rutas privadas API responden 401. Un rol no permitido recibe 403 antes del enlace de modelos y de la validación del payload.

## Inventario completo

Las columnas muestran los controles efectivos después de SCRUM-782/783. `auth:sanctum` autentica; `role` limita rol y aprobación; `admin` restringe al administrador. Los controles por asignación/propiedad se detallan después de la tabla.

| Método | Endpoint | Roles permitidos | Control de entrada | Acción |
| --- | --- | --- | --- | --- |
| GET / HEAD | `/` | Público | `Sin sesión` | `Closure` |
| GET / HEAD | `/api/admin/dashboard-summary` | A | `auth:sanctum + admin` | `AdminDashboardController@summary` |
| GET / HEAD | `/api/admin/family-caregivers` | A | `auth:sanctum + admin` | `AdminUserController@familyCaregivers` |
| POST | `/api/admin/incidents` | A | `auth:sanctum + admin` | `ProfessionalIncidentController@store` |
| GET / HEAD | `/api/admin/medication-statistics` | A | `auth:sanctum + admin` | `AdminDashboardController@medicationStatistics` |
| GET / HEAD | `/api/admin/medications/inventory` | A | `auth:sanctum + admin` | `MedicationInventoryController@index` |
| POST | `/api/admin/medications/inventory` | A | `auth:sanctum + admin` | `MedicationInventoryController@store` |
| PUT | `/api/admin/medications/inventory/{inventoryItem}` | A | `auth:sanctum + admin` | `MedicationInventoryController@update` |
| DELETE | `/api/admin/medications/inventory/{inventoryItem}` | A | `auth:sanctum + admin` | `MedicationInventoryController@destroy` |
| PATCH | `/api/admin/medications/inventory/{inventoryItem}/stock` | A | `auth:sanctum + admin` | `MedicationInventoryController@adjustStock` |
| GET / HEAD | `/api/admin/mobility-exercises` | A | `auth:sanctum + admin` | `MobilityExerciseController@index` |
| POST | `/api/admin/mobility-exercises` | A | `auth:sanctum + admin` | `MobilityExerciseController@store` |
| GET / HEAD | `/api/admin/mobility-exercises/{mobilityExercise}` | A | `auth:sanctum + admin` | `MobilityExerciseController@show` |
| PUT | `/api/admin/mobility-exercises/{mobilityExercise}` | A | `auth:sanctum + admin` | `MobilityExerciseController@update` |
| DELETE | `/api/admin/mobility-exercises/{mobilityExercise}` | A | `auth:sanctum + admin` | `MobilityExerciseController@destroy` |
| GET / HEAD | `/api/admin/older-adults` | A | `auth:sanctum + admin` | `OlderAdultController@index` |
| POST | `/api/admin/older-adults` | A | `auth:sanctum + admin` | `OlderAdultController@store` |
| GET / HEAD | `/api/admin/older-adults/{olderAdult}` | A | `auth:sanctum + admin` | `OlderAdultController@show` |
| PUT | `/api/admin/older-adults/{olderAdult}` | A | `auth:sanctum + admin` | `OlderAdultController@update` |
| DELETE | `/api/admin/older-adults/{olderAdult}` | A | `auth:sanctum + admin` | `OlderAdultController@destroy` |
| GET / HEAD | `/api/admin/professional-caregivers` | A | `auth:sanctum + admin` | `AdminUserController@professionalCaregivers` |
| GET / HEAD | `/api/admin/schedules` | A | `auth:sanctum + admin` | `CaregiverScheduleController@adminIndex` |
| POST | `/api/admin/schedules` | A | `auth:sanctum + admin` | `CaregiverScheduleController@adminStore` |
| GET / HEAD | `/api/admin/schedules/calendar` | A | `auth:sanctum + admin` | `CaregiverScheduleController@calendar` |
| DELETE | `/api/admin/schedules/{schedule}` | A | `auth:sanctum + admin` | `CaregiverScheduleController@destroy` |
| PATCH | `/api/admin/schedules/{schedule}/change-request/approve` | A | `auth:sanctum + admin` | `CaregiverScheduleController@approveChangeRequest` |
| PATCH | `/api/admin/schedules/{schedule}/change-request/reject` | A | `auth:sanctum + admin` | `CaregiverScheduleController@rejectChangeRequest` |
| GET / HEAD | `/api/admin/users` | A | `auth:sanctum + admin` | `AdminUserController@index` |
| POST | `/api/admin/users` | A | `auth:sanctum + admin` | `AdminUserController@store` |
| GET / HEAD | `/api/admin/users/{user}` | A | `auth:sanctum + admin` | `AdminUserController@show` |
| PUT | `/api/admin/users/{user}` | A | `auth:sanctum + admin` | `AdminUserController@update` |
| DELETE | `/api/admin/users/{user}` | A | `auth:sanctum + admin` | `AdminUserController@destroy` |
| PATCH | `/api/admin/users/{user}/approve` | A | `auth:sanctum + admin` | `AdminUserController@approve` |
| DELETE | `/api/admin/users/{user}/reject` | A | `auth:sanctum + admin` | `AdminUserController@reject` |
| GET / HEAD | `/api/admin/vacation-requests` | A | `auth:sanctum + admin` | `VacationRequestController@adminIndex` |
| PATCH | `/api/admin/vacation-requests/{vacationRequest}/approve` | A | `auth:sanctum + admin` | `VacationRequestController@approve` |
| PATCH | `/api/admin/vacation-requests/{vacationRequest}/reject` | A | `auth:sanctum + admin` | `VacationRequestController@reject` |
| GET / HEAD | `/api/family/incidents` | F | `auth:sanctum + role` | `FamilyCareController@incidents` |
| GET / HEAD | `/api/family/older-adults` | F | `auth:sanctum + role` | `FamilyCareController@olderAdults` |
| GET / HEAD | `/api/family/older-adults/{olderAdult}` | F | `auth:sanctum + role` | `FamilyCareController@olderAdult` |
| GET / HEAD | `/api/family/older-adults/{olderAdult}/incidents` | F | `auth:sanctum + role` | `FamilyCareController@olderAdultIncidents` |
| GET / HEAD | `/api/family/overview` | F | `auth:sanctum + role` | `FamilyCareController@overview` |
| GET / HEAD | `/api/family/routine` | F | `auth:sanctum + role` | `FamilyCareController@routine` |
| GET / HEAD | `/api/family/routines` | F | `auth:sanctum + role` | `FamilyCareController@routine` |
| GET / HEAD | `/api/incidents` | A, P, F | `auth:sanctum + role` | `IncidentController@index` |
| GET / HEAD | `/api/incidents/today` | A, P, F | `auth:sanctum + role` | `IncidentController@today` |
| POST | `/api/login` | Público | `Validación + límite de intentos fallidos` | `AuthController@login` |
| POST | `/api/logout` | Sesión | `auth:sanctum` | `AuthController@logout` |
| GET / HEAD | `/api/me` | Sesión | `auth:sanctum` | `AuthController@me` |
| PUT | `/api/me` | Sesión | `auth:sanctum` | `AuthController@updateMe` |
| POST | `/api/medications/{assignment}/taken` | P | `auth:sanctum + role` | `MedicationAdministrationController@markTaken` |
| GET / HEAD | `/api/mobility-exercises` | A, P | `auth:sanctum + role` | `MobilityExerciseController@index` |
| GET / HEAD | `/api/mobility-exercises/{mobilityExercise}` | A, P | `auth:sanctum + role` | `MobilityExerciseController@show` |
| GET / HEAD | `/api/ping` | Público | `Sin sesión` | `Closure` |
| POST | `/api/professional/incidents` | P | `auth:sanctum + role` | `ProfessionalIncidentController@store` |
| PATCH | `/api/professional/incidents/{incident}` | P | `auth:sanctum + role` | `ProfessionalIncidentController@update` |
| GET / HEAD | `/api/professional/older-adults` | P | `auth:sanctum + role` | `ProfessionalCareController@olderAdults` |
| GET / HEAD | `/api/professional/older-adults/{olderAdult}` | P | `auth:sanctum + role` | `ProfessionalCareController@olderAdult` |
| GET / HEAD | `/api/professional/overview` | P | `auth:sanctum + role` | `ProfessionalCareController@overview` |
| GET / HEAD | `/api/professional/reminders` | P | `auth:sanctum + role` | `ReminderController@index` |
| GET / HEAD | `/api/professional/routine-notes` | P | `auth:sanctum + role` | `ProfessionalRoutineNoteController@index` |
| POST | `/api/professional/routine-notes` | P | `auth:sanctum + role` | `ProfessionalRoutineNoteController@store` |
| GET / HEAD | `/api/professional/routine-notes/{routineNote}` | P | `auth:sanctum + role` | `ProfessionalRoutineNoteController@show` |
| PUT | `/api/professional/routine-notes/{routineNote}` | P | `auth:sanctum + role` | `ProfessionalRoutineNoteController@update` |
| DELETE | `/api/professional/routine-notes/{routineNote}` | P | `auth:sanctum + role` | `ProfessionalRoutineNoteController@destroy` |
| GET / HEAD | `/api/professional/routines` | P | `auth:sanctum + role` | `ProfessionalCareController@routine` |
| GET / HEAD | `/api/professional/schedules` | P | `auth:sanctum + role` | `ProfessionalCareController@schedules` |
| GET / HEAD | `/api/professional/vacation-requests` | P | `auth:sanctum + role` | `VacationRequestController@index` |
| POST | `/api/professional/vacation-requests` | P | `auth:sanctum + role` | `VacationRequestController@store` |
| POST | `/api/register` | Público | `throttle:registration + validación` | `AuthController@register` |
| GET / HEAD | `/api/rutinas` | A, P, F | `auth:sanctum + role` | `RutinaController@index` |
| POST | `/api/rutinas` | A, P, F | `auth:sanctum + role` | `RutinaController@store` |
| PUT | `/api/rutinas/{rutina}` | A, P, F | `auth:sanctum + role` | `RutinaController@update` |
| DELETE | `/api/rutinas/{rutina}` | A, P, F | `auth:sanctum + role` | `RutinaController@destroy` |
| PATCH | `/api/rutinas/{rutina}/completar` | A, P, F | `auth:sanctum + role` | `RutinaController@complete` |
| POST | `/api/schedules` | P | `auth:sanctum + role` | `CaregiverScheduleController@store` |
| PUT | `/api/schedules/{schedule}` | A, P | `auth:sanctum + role` | `CaregiverScheduleController@update` |
| POST | `/api/schedules/{schedule}/change-request` | P | `auth:sanctum + role` | `CaregiverScheduleController@requestChange` |
| GET / HEAD | `/sanctum/csrf-cookie` | Público | `web (cookie CSRF, sin datos privados)` | `CsrfCookieController@show` |
| GET / HEAD | `/storage/{path}` | Portador de URL firmada vigente | `Firma relativa y expiración (ServeFile)` | `Closure` |
| GET / HEAD | `/up` | Público | `Sin sesión` | `Closure` |

## Límites por recurso que siguen vigentes

| Recurso / operación | Alcance autorizado |
| --- | --- |
| `/me`, `/logout` | Perfil y credencial del usuario actual. El perfil no permite elevar rol ni aprobación. |
| Incidentes generales | A consulta todos; P/F solamente los adultos asignados. Se conserva la compatibilidad con incidentes heredados por nombre. |
| Consultas `/family/*` | F y adultos asociados por `family_caregiver_id`; compatibilidad heredada por nombre cuando no existe ese ID. |
| Consultas `/professional/*` | P y adultos asociados por `professional_caregiver_id`; horarios del propio profesional. |
| Crear/editar incidentes profesionales | P y adulto asignado. A registra incidentes por `/api/admin/incidents`. |
| Notas profesionales | P, adulto asignado y autor propietario de la nota para consultar, editar o eliminar una nota individual. |
| Marcar medicamento tomado | P y adulto asignado a ese profesional. |
| Crear horario propio / solicitar cambio | P; solo su propio horario. |
| Actualizar horario | A puede actualizar cualquier horario; P solamente el propio. |
| Rutinas (listar, crear, editar, completar, eliminar) | A; P/F aprobados y adultos asignados según `RutinaPolicy`. Se preservan las operaciones familiares existentes. |
| Ejercicios de movilidad | A consulta todos y administra el catálogo; P consulta los activos. F sin acceso al catálogo. |
| Vacaciones | P consulta/crea solicitudes propias; A consulta y resuelve las pendientes. |
| Usuarios, adultos mayores, inventario de medicamentos, dashboard y estadísticas administrativos | Exclusivamente A. |
| `/storage/{path}` | Ruta del framework para archivos locales privados: exige una URL con firma relativa válida y vigente. No usa sesión Sanctum; la firma es la credencial de acceso. No hay emisión de estas URLs en los servicios actuales. |

## Hallazgos y entrega por subtarea

1. **SCRUM-781:** inventario de todas las rutas HTTP registradas, roles admitidos y límites por recurso. Comandos de consola y archivos estáticos del frontend no son endpoints del backend.
2. **SCRUM-782:** se verificó que todas las rutas privadas API tienen `auth:sanctum`; logout se integró en el grupo autenticado. Pruebas recorren todas esas rutas sin sesión y con token inválido; además comprueban un token real, expirado y revocado por logout. Las únicas rutas públicas API son ping, login y register. `/`, `/up` y la cookie CSRF son endpoints públicos de infraestructura; almacenamiento usa firma.
3. **SCRUM-783:** middleware `EnsureRole` aplicado a las rutas de cuidado y grupos familiar/profesional, con compatibilidad de alias y aprobación. Los controles de rol/administrador se ejecutan antes del enlace de modelos. Se cerró la consulta sin filtro que `IncidentListingService` permitía a roles desconocidos. Se conservaron controles de asignación y propiedad en servicios/Requests/Policy.

**SCRUM-784 queda pendiente:** crear o completar Policies para otros recursos sensibles. Esta entrega no crea ni modifica Policies.

## Verificación reproducible

Desde `backend`:

```powershell
php artisan route:list --json
php vendor/phpunit/phpunit/phpunit tests/Feature/BackendAccessControlTest.php --colors=never
php vendor/phpunit/phpunit/phpunit --colors=never
```

`BackendAccessControlTest` comprueba autenticación de todas las rutas privadas, cruces de roles, roles desconocidos, cuidadores pendientes, alias aprobados, aprobación retirada con token existente y rechazo directo de roles desconocidos en el servicio de incidentes. La suite previa verifica operaciones permitidas y aislamiento por adulto/propietario.

El entorno local instalado usa PHP 8.2, Laravel 11 y PHPUnit 11; `composer.json` solicita PHP 8.3+, Laravel 13 y PHPUnit 12. Los resultados locales deben interpretarse con esa diferencia; no se actualizaron dependencias en estas subtareas.

Resultado local del 2026-10-09: **22 pruebas nuevas, 893 aserciones, sin fallos**; suite completa: **208 pruebas, 2553 aserciones, sin fallos**. Los cinco archivos PHP creados/modificados también pasaron `php -l`. PHPUnit se ejecutó fuera del aislamiento porque su comprobación de lectura del bootstrap fallaba dentro del entorno restringido. No se ejecutaron acciones de Git.
