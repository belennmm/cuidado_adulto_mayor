# SCRUM-784, SCRUM-785 y SCRUM-786

Historia SCRUM-779 (A01). Fecha: 2026-10-09. Esta entrega abarca únicamente estas tres subtareas; no ejecuta acciones de Git.

## SCRUM-784: Policies para recursos sensibles

Se registraron explícitamente nueve Policies en `AppServiceProvider`: ocho nuevas y la Policy de rutinas existente actualizada. Las 71 operaciones API de cuidado/administración declaran su autorización con `can`; las tres operaciones de cuenta (GET/PUT `/api/me` y POST `/api/logout`) mantienen su contrato de sesión propia.

| Policy | Reglas principales |
| --- | --- |
| `UserPolicy` | Gestión de usuarios exclusivamente para `admin`. |
| `OlderAdultPolicy` | Consulta de adulto por administrador o cuidador aprobado y asignado; altas, cambios y bajas exclusivamente para administrador. Los listados de cuidadores conservan filtros por asignación. |
| `IncidentPolicy` | Listado por rol válido; creación para administrador o profesional aprobado, con comprobación adicional del adulto asignado; actualización para profesional asignado. |
| `RoutineNotePolicy` | Profesional aprobado, adulto asignado y autor propietario para consultar/cambiar/eliminar la nota individual. La creación y el listado semanal también verifican al adulto. |
| `RutinaPolicy` | Administrador o cuidador aprobado y asignado. Se retiró el permiso administrativo general de `before`; cada operación admitida tiene una regla explícita. |
| `CaregiverSchedulePolicy` | Profesional aprobado y horario propio; administrador en las operaciones administrativas explícitas. Solicitar un cambio sigue siendo una operación del profesional propietario. |
| `OlderAdultMedicationPolicy` | Inventario y ajustes administrativos; registro de toma por profesional aprobado y asignado al adulto. Recordatorios exclusivos del profesional. |
| `MobilityExercisePolicy` | Administrador gestiona; profesional aprobado consulta ejercicios activos. Se conserva 404 para ejercicios inactivos consultados por profesionales. |
| `VacationRequestPolicy` | Profesional aprobado crea y consulta solicitudes propias; administrador consulta y revisa. No se concede al administrador la operación de solicitar vacaciones como profesional. |

`ResourceAccess` reúne las comprobaciones de rol, aprobación, ID de propietario y asignación. Se conservan los alias de cuidadores. El rol administrativo admitido es exactamente `admin`, igual que en `EnsureAdmin`; `administrador` no otorga un permiso alternativo.

## SCRUM-785: validar propiedad y asignación

Los middleware `can` ejecutan la Policy después de enlazar el modelo y antes de ejecutar el controlador o validar/modificar el payload. Las operaciones con un adulto seleccionado en el cuerpo o query verifican además la Policy en el servicio una vez identificado ese adulto.

- Las consultas familiares usan `family_caregiver_id`; las profesionales, `professional_caregiver_id`.
- Una coincidencia de `caregiver_family` con el nombre del usuario no concede acceso.
- Los incidentes de cuidadores se vinculan mediante `older_adult_id`. Un `adult_name` coincidente sin ese ID no concede acceso.
- Una nota exige tanto autor propietario como asignación actual al adulto. Conservar la autoría después de reasignar al adulto no conserva el acceso.
- Las rutinas filtradas por adulto también validan ese adulto al invocar directamente `RoutineService::listFor`.
- La toma de medicamentos exige que el adulto de la asignación pertenezca al profesional actual.
- Horarios y vacaciones conservan sus restricciones por `user_id` y sus operaciones administrativas explícitas.

### Datos heredados

Los adultos que solo tengan `caregiver_family`, sin `family_caregiver_id`, dejan de ser visibles para familiares hasta que un administrador registre su asignación por ID. Los incidentes sin `older_adult_id` siguen visibles para administradores en el listado general, pero no para cuidadores. No se dedujeron vínculos por nombres ni se modificaron datos existentes automáticamente.

Tres pruebas de `FamilyCareEndpointsTest` usaban solo nombres en sus fixtures. Ahora crean las relaciones por ID y mantienen sus comprobaciones de visualización, aislamiento y preservación del vínculo al editar el perfil. Una prueba nueva comprueba expresamente que los nombres por sí solos no conceden acceso.

## SCRUM-786: denegación por defecto

`RequireAccessRule`, agregado al grupo API, rechaza cualquier ruta sin un contrato de autorización explícito. Solo admite como excepciones ping, login, registro y las operaciones autenticadas de la cuenta propia. Para los demás endpoints exige tanto `auth:sanctum` como una autorización `can`, que Gate debe resolver satisfactoriamente.

Una ruta nueva con solo autenticación o middleware de rol queda denegada. Una habilidad inexistente también queda denegada, incluso para administradores. Los tipos de cuidador desconocidos se rechazan y nunca se interpretan como familiar/profesional por defecto. El listado de rutinas rechaza roles sin permiso y conserva una rama final que nunca devuelve datos sin filtro.

## Validación

Desde `backend`:

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/ResourcePolicyAccessTest.php --colors=never
php vendor/phpunit/phpunit/phpunit --colors=never
```

Las pruebas nuevas comprueban Policies registradas, permisos no definidos, roles desconocidos o pendientes sin depender del middleware de ruta, rutas sin Policy, rechazo de tipos de cuidador desconocidos, llamadas directas al servicio de rutinas, vínculos por ID y pérdida de acceso tras reasignación. Las pruebas previas comprueban los flujos permitidos y las restricciones por rol y propietario.

Resultado local del 2026-10-09: **15 pruebas nuevas, 223 aserciones, sin fallos**. Suite completa: **223 pruebas, 2776 aserciones, sin fallos**. Pint aplicó el formato del proyecto a los archivos PHP de esta entrega. Las pruebas y Pint se ejecutaron fuera del aislamiento por las restricciones de lectura/escritura de esos ejecutables en Windows.

El entorno local continúa en PHP 8.2 / Laravel 11 / PHPUnit 11; `composer.json` declara PHP 8.3+ / Laravel 13 / PHPUnit 12. No se cambiaron dependencias. Las versiones declaradas necesitan validación adicional en su entorno correspondiente.
