# SCRUM-781: inventario de endpoints y roles del backend

Fecha: 2026-10-09. Historia SCRUM-779 (A01), sprint 9.

Cobertura: **81 rutas HTTP**, incluidas **77 rutas API** (3 públicas y 74 privadas).

Inventario obtenido de `php artisan route:list --json`, contrastado con `routes/api.php`, `routes/web.php`, `bootstrap/app.php`, Requests, servicios y `RutinaPolicy`. Cada fila representa una combinación de ruta y métodos registrados; GET incluye HEAD. Las rutas API llevan el prefijo `/api`.

## Roles y reglas de acceso

- **A**: administrador (`admin`), aprobado. El middleware administrativo mantiene la comprobación exacta de este valor. No obtiene acceso implícito a las rutas de cuidadores.
- **P**: cuidador profesional (`profesional` o `cuidador_profesional`), aprobado.
- **F**: cuidador familiar (`familiar` o `cuidador_familiar`), aprobado.
- **Sesión**: un usuario aprobado, de rol reconocido y con credenciales vigentes puede consultar/editar su propio perfil y cerrar su sesión, con el alcance de token correspondiente. Estas operaciones no conceden acceso a información de cuidado. Retirar la aprobación mediante los servicios revoca sus credenciales, incluidas las del administrador.
- Un rol desconocido o vacío no concede acceso a recursos de cuidado, aunque el usuario esté aprobado.
- El inicio de sesión exige rol reconocido y aprobación. Retirar la aprobación mediante el modelo revoca los tokens existentes: su siguiente uso recibe 401. Los middleware y Policies también rechazan con 403 a usuarios autenticados sin aprobación.
- Sin autenticación válida, las rutas privadas API responden 401. Un rol no permitido recibe 403 antes del enlace de modelos y de la validación del payload.
- Dentro de un módulo permitido, consultar o modificar un recurso ajeno devuelve el mismo 404 y mensaje genérico que un identificador inexistente: `Recurso no encontrado.`
- Cada solicitud privada recarga usuario y credencial vigente antes de comprobar roles. También exige el alcance de lectura/escritura del token y los permisos actuales del usuario; una capacidad del token no sustituye la Policy.

## Inventario completo

Las columnas muestran los controles efectivos después de SCRUM-791/792/793/794. `auth:sanctum` autentica; `role` limita rol y aprobación; `admin` restringe al administrador aprobado; `can` ejecuta la Policy indicada. El grupo API aplica además `ValidateCurrentAccess`, `EnsureTokenScope` y `RequireAccessRule` para comprobar identidad/credenciales vigentes, capacidades del token y contrato explícito. Los controles por asignación/propiedad se detallan después de la tabla.

| Método | Endpoint | Roles permitidos | Control de entrada | Acción |
| --- | --- | --- | --- | --- |
| GET / HEAD | `/` | Público | `Sin sesión` | `Closure` |
| GET / HEAD | `/api/admin/dashboard-summary` | A | `auth:sanctum + admin + can:viewAny,User` | `AdminDashboardController@summary` |
| GET / HEAD | `/api/admin/family-caregivers` | A | `auth:sanctum + admin + can:viewAny,User` | `AdminUserController@familyCaregivers` |
| POST | `/api/admin/incidents` | A | `auth:sanctum + admin + can:create,Incident` | `ProfessionalIncidentController@store` |
| GET / HEAD | `/api/admin/medication-statistics` | A | `auth:sanctum + admin + can:viewAny,OlderAdultMedication` | `AdminDashboardController@medicationStatistics` |
| GET / HEAD | `/api/admin/medications/inventory` | A | `auth:sanctum + admin + can:viewAny,OlderAdultMedication` | `MedicationInventoryController@index` |
| POST | `/api/admin/medications/inventory` | A | `auth:sanctum + admin + can:create,OlderAdultMedication` | `MedicationInventoryController@store` |
| PUT | `/api/admin/medications/inventory/{inventoryItem}` | A | `auth:sanctum + admin + can:update,inventoryItem` | `MedicationInventoryController@update` |
| DELETE | `/api/admin/medications/inventory/{inventoryItem}` | A | `auth:sanctum + admin + can:delete,inventoryItem` | `MedicationInventoryController@destroy` |
| PATCH | `/api/admin/medications/inventory/{inventoryItem}/stock` | A | `auth:sanctum + admin + can:adjustStock,inventoryItem` | `MedicationInventoryController@adjustStock` |
| GET / HEAD | `/api/admin/mobility-exercises` | A | `auth:sanctum + admin + can:viewAny,MobilityExercise` | `MobilityExerciseController@index` |
| POST | `/api/admin/mobility-exercises` | A | `auth:sanctum + admin + can:create,MobilityExercise` | `MobilityExerciseController@store` |
| GET / HEAD | `/api/admin/mobility-exercises/{mobilityExercise}` | A | `auth:sanctum + admin + can:view,mobilityExercise` | `MobilityExerciseController@show` |
| PUT | `/api/admin/mobility-exercises/{mobilityExercise}` | A | `auth:sanctum + admin + can:update,mobilityExercise` | `MobilityExerciseController@update` |
| DELETE | `/api/admin/mobility-exercises/{mobilityExercise}` | A | `auth:sanctum + admin + can:delete,mobilityExercise` | `MobilityExerciseController@destroy` |
| GET / HEAD | `/api/admin/older-adults` | A | `auth:sanctum + admin + can:viewAny,OlderAdult` | `OlderAdultController@index` |
| POST | `/api/admin/older-adults` | A | `auth:sanctum + admin + can:create,OlderAdult` | `OlderAdultController@store` |
| GET / HEAD | `/api/admin/older-adults/{olderAdult}` | A | `auth:sanctum + admin + can:view,olderAdult` | `OlderAdultController@show` |
| PUT | `/api/admin/older-adults/{olderAdult}` | A | `auth:sanctum + admin + can:update,olderAdult` | `OlderAdultController@update` |
| DELETE | `/api/admin/older-adults/{olderAdult}` | A | `auth:sanctum + admin + can:delete,olderAdult` | `OlderAdultController@destroy` |
| GET / HEAD | `/api/admin/professional-caregivers` | A | `auth:sanctum + admin + can:viewAny,User` | `AdminUserController@professionalCaregivers` |
| GET / HEAD | `/api/admin/schedules` | A | `auth:sanctum + admin + can:manage,CaregiverSchedule` | `CaregiverScheduleController@adminIndex` |
| POST | `/api/admin/schedules` | A | `auth:sanctum + admin + can:manage,CaregiverSchedule` | `CaregiverScheduleController@adminStore` |
| GET / HEAD | `/api/admin/schedules/calendar` | A | `auth:sanctum + admin + can:manage,CaregiverSchedule` | `CaregiverScheduleController@calendar` |
| DELETE | `/api/admin/schedules/{schedule}` | A | `auth:sanctum + admin + can:delete,schedule` | `CaregiverScheduleController@destroy` |
| PATCH | `/api/admin/schedules/{schedule}/change-request/approve` | A | `auth:sanctum + admin + can:review,schedule` | `CaregiverScheduleController@approveChangeRequest` |
| PATCH | `/api/admin/schedules/{schedule}/change-request/reject` | A | `auth:sanctum + admin + can:review,schedule` | `CaregiverScheduleController@rejectChangeRequest` |
| GET / HEAD | `/api/admin/users` | A | `auth:sanctum + admin + can:viewAny,User` | `AdminUserController@index` |
| POST | `/api/admin/users` | A | `auth:sanctum + admin + can:create,User` | `AdminUserController@store` |
| GET / HEAD | `/api/admin/users/{user}` | A | `auth:sanctum + admin + can:view,user` | `AdminUserController@show` |
| PUT | `/api/admin/users/{user}` | A | `auth:sanctum + admin + can:update,user` | `AdminUserController@update` |
| DELETE | `/api/admin/users/{user}` | A | `auth:sanctum + admin + can:delete,user` | `AdminUserController@destroy` |
| PATCH | `/api/admin/users/{user}/approve` | A | `auth:sanctum + admin + can:approve,user` | `AdminUserController@approve` |
| DELETE | `/api/admin/users/{user}/reject` | A | `auth:sanctum + admin + can:reject,user` | `AdminUserController@reject` |
| GET / HEAD | `/api/admin/vacation-requests` | A | `auth:sanctum + admin + can:manage,VacationRequest` | `VacationRequestController@adminIndex` |
| PATCH | `/api/admin/vacation-requests/{vacationRequest}/approve` | A | `auth:sanctum + admin + can:review,vacationRequest` | `VacationRequestController@approve` |
| PATCH | `/api/admin/vacation-requests/{vacationRequest}/reject` | A | `auth:sanctum + admin + can:review,vacationRequest` | `VacationRequestController@reject` |
| GET / HEAD | `/api/family/incidents` | F | `auth:sanctum + role + can:viewAny,Incident` | `FamilyCareController@incidents` |
| GET / HEAD | `/api/family/older-adults` | F | `auth:sanctum + role + can:viewAny,OlderAdult` | `FamilyCareController@olderAdults` |
| GET / HEAD | `/api/family/older-adults/{olderAdult}` | F | `auth:sanctum + role + can:view,olderAdult` | `FamilyCareController@olderAdult` |
| GET / HEAD | `/api/family/older-adults/{olderAdult}/incidents` | F | `auth:sanctum + role + can:view,olderAdult` | `FamilyCareController@olderAdultIncidents` |
| GET / HEAD | `/api/family/overview` | F | `auth:sanctum + role + can:viewAny,OlderAdult` | `FamilyCareController@overview` |
| GET / HEAD | `/api/family/routine` | F | `auth:sanctum + role + can:viewAny,Rutina` | `FamilyCareController@routine` |
| GET / HEAD | `/api/family/routines` | F | `auth:sanctum + role + can:viewAny,Rutina` | `FamilyCareController@routine` |
| GET / HEAD | `/api/incidents` | A, P, F | `auth:sanctum + role + can:viewAny,Incident` | `IncidentController@index` |
| GET / HEAD | `/api/incidents/today` | A, P, F | `auth:sanctum + role + can:viewAny,Incident` | `IncidentController@today` |
| POST | `/api/login` | Público | `Validación + límite de intentos fallidos` | `AuthController@login` |
| POST | `/api/logout` | Sesión | `auth:sanctum` | `AuthController@logout` |
| GET / HEAD | `/api/me` | Sesión | `auth:sanctum` | `AuthController@me` |
| PUT | `/api/me` | Sesión | `auth:sanctum` | `AuthController@updateMe` |
| POST | `/api/medications/{assignment}/taken` | P | `auth:sanctum + role + can:markTaken,assignment` | `MedicationAdministrationController@markTaken` |
| GET / HEAD | `/api/mobility-exercises` | A, P | `auth:sanctum + role + can:viewAny,MobilityExercise` | `MobilityExerciseController@index` |
| GET / HEAD | `/api/mobility-exercises/{mobilityExercise}` | A, P | `auth:sanctum + role + can:view,mobilityExercise` | `MobilityExerciseController@show` |
| GET / HEAD | `/api/ping` | Público | `Sin sesión` | `Closure` |
| POST | `/api/professional/incidents` | P | `auth:sanctum + role + can:create,Incident` | `ProfessionalIncidentController@store` |
| PATCH | `/api/professional/incidents/{incident}` | P | `auth:sanctum + role + can:update,incident` | `ProfessionalIncidentController@update` |
| GET / HEAD | `/api/professional/older-adults` | P | `auth:sanctum + role + can:viewAny,OlderAdult` | `ProfessionalCareController@olderAdults` |
| GET / HEAD | `/api/professional/older-adults/{olderAdult}` | P | `auth:sanctum + role + can:view,olderAdult` | `ProfessionalCareController@olderAdult` |
| GET / HEAD | `/api/professional/overview` | P | `auth:sanctum + role + can:viewAny,OlderAdult` | `ProfessionalCareController@overview` |
| GET / HEAD | `/api/professional/reminders` | P | `auth:sanctum + role + can:reminders,OlderAdultMedication` | `ReminderController@index` |
| GET / HEAD | `/api/professional/routine-notes` | P | `auth:sanctum + role + can:viewAny,RoutineNote` | `ProfessionalRoutineNoteController@index` |
| POST | `/api/professional/routine-notes` | P | `auth:sanctum + role + can:create,RoutineNote` | `ProfessionalRoutineNoteController@store` |
| GET / HEAD | `/api/professional/routine-notes/{routineNote}` | P | `auth:sanctum + role + can:view,routineNote` | `ProfessionalRoutineNoteController@show` |
| PUT | `/api/professional/routine-notes/{routineNote}` | P | `auth:sanctum + role + can:update,routineNote` | `ProfessionalRoutineNoteController@update` |
| DELETE | `/api/professional/routine-notes/{routineNote}` | P | `auth:sanctum + role + can:delete,routineNote` | `ProfessionalRoutineNoteController@destroy` |
| GET / HEAD | `/api/professional/routines` | P | `auth:sanctum + role + can:viewAny,Rutina` | `ProfessionalCareController@routine` |
| GET / HEAD | `/api/professional/schedules` | P | `auth:sanctum + role + can:viewAny,CaregiverSchedule` | `ProfessionalCareController@schedules` |
| GET / HEAD | `/api/professional/vacation-requests` | P | `auth:sanctum + role + can:viewAny,VacationRequest` | `VacationRequestController@index` |
| POST | `/api/professional/vacation-requests` | P | `auth:sanctum + role + can:create,VacationRequest` | `VacationRequestController@store` |
| POST | `/api/register` | Público | `throttle:registration + validación` | `AuthController@register` |
| GET / HEAD | `/api/rutinas` | A, P, F | `auth:sanctum + role + can:viewAny,Rutina` | `RutinaController@index` |
| POST | `/api/rutinas` | A, P, F | `auth:sanctum + role + can:create,Rutina` | `RutinaController@store` |
| PUT | `/api/rutinas/{rutina}` | A, P, F | `auth:sanctum + role + can:update,rutina` | `RutinaController@update` |
| DELETE | `/api/rutinas/{rutina}` | A, P, F | `auth:sanctum + role + can:delete,rutina` | `RutinaController@destroy` |
| PATCH | `/api/rutinas/{rutina}/completar` | A, P, F | `auth:sanctum + role + can:complete,rutina` | `RutinaController@complete` |
| POST | `/api/schedules` | P | `auth:sanctum + role + can:create,CaregiverSchedule` | `CaregiverScheduleController@store` |
| PUT | `/api/schedules/{schedule}` | A, P | `auth:sanctum + role + can:update,schedule` | `CaregiverScheduleController@update` |
| POST | `/api/schedules/{schedule}/change-request` | P | `auth:sanctum + role + can:requestChange,schedule` | `CaregiverScheduleController@requestChange` |
| GET / HEAD | `/sanctum/csrf-cookie` | Público | `web (cookie CSRF, sin datos privados)` | `CsrfCookieController@show` |
| GET / HEAD | `/storage/{path}` | Portador de URL firmada vigente | `Firma relativa y expiración (ServeFile)` | `Closure` |
| GET / HEAD | `/up` | Público | `Sin sesión` | `Closure` |

## Límites por recurso que siguen vigentes

| Recurso / operación | Alcance autorizado |
| --- | --- |
| `/me`, `/logout` | Perfil y credencial del usuario actual. El perfil no permite elevar rol ni aprobación. |
| Incidentes generales | A consulta todos; P/F solamente los incidentes vinculados por `older_adult_id` a sus adultos asignados. Un nombre coincidente no concede acceso. |
| Consultas `/family/*` | F y adultos asociados por `family_caregiver_id`. Los nombres heredados no conceden acceso. |
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

4. **SCRUM-784/785/786:** Policies explícitas, validación por ID de propietario/asignación y denegación por defecto. Detalle y efectos sobre datos heredados en [POLICIES_ASIGNACION_DENEGACION.md](POLICIES_ASIGNACION_DENEGACION.md).

5. **SCRUM-787/788/789/790:** protección de campos, revocación de credenciales, respuestas uniformes para recursos ajenos y pruebas de IDOR/escalamiento. Detalle en [CAMPOS_TOKENS_IDOR.md](CAMPOS_TOKENS_IDOR.md).

6. **SCRUM-791/792/793/794:** permisos vigentes por solicitud, protección frente a enumeración de IDs, mínimos privilegios y bloqueo de asignación masiva. Detalle y requisitos de producción en [PERMISOS_VIGENTES_MINIMOS_PRIVILEGIOS.md](PERMISOS_VIGENTES_MINIMOS_PRIVILEGIOS.md).

## Verificación reproducible

Desde `backend`:

```powershell
php artisan route:list --json
php vendor/phpunit/phpunit/phpunit tests/Feature/BackendAccessControlTest.php --colors=never
php vendor/phpunit/phpunit/phpunit --colors=never
```

`BackendAccessControlTest` comprueba autenticación de todas las rutas privadas, cruces de roles, roles desconocidos, cuidadores pendientes, alias aprobados, aprobación retirada con token existente y rechazo directo de roles desconocidos en el servicio de incidentes. La suite previa verifica operaciones permitidas y aislamiento por adulto/propietario.

El entorno local instalado usa PHP 8.2, Laravel 11 y PHPUnit 11; `composer.json` solicita PHP 8.3+, Laravel 13 y PHPUnit 12. Los resultados locales deben interpretarse con esa diferencia; no se actualizaron dependencias en estas subtareas.

Validación inicial SCRUM-781/782/783 del 2026-10-09: **22 pruebas nuevas, 893 aserciones, sin fallos**; suite completa inicial: **208 pruebas, 2553 aserciones, sin fallos**. Los cinco archivos PHP de esa entrega también pasaron `php -l`.

Validación SCRUM-784/785/786 del 2026-10-09: **15 pruebas adicionales, 223 aserciones, sin fallos**; suite completa de ese bloque: **223 pruebas, 2776 aserciones, sin fallos**.

Validación SCRUM-787/788/789/790 del 2026-10-09: **30 pruebas en los dos archivos nuevos, 581 aserciones, sin fallos**, más un caso de administrador desaprobado en `ResourcePolicyAccessTest`. Suite completa de ese bloque: **254 pruebas, 3374 aserciones, sin fallos**.

Validación SCRUM-791/792/793/794 del 2026-10-09: **48 casos adicionales**; suite completa actual: **302 pruebas, 3720 aserciones, sin fallos**. PHPUnit y Pint se ejecutaron fuera del aislamiento por las restricciones locales de Windows. No se ejecutaron acciones de Git.
