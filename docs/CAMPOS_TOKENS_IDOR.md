# SCRUM-787, SCRUM-788, SCRUM-789 y SCRUM-790

Historia SCRUM-779 (A01). Fecha: 2026-10-09. Bloque de cuatro subtareas, sin acciones de Git.

## SCRUM-787: campos protegidos

`AuthService::updateProfile` admite únicamente los campos de perfil y la operación explícita de cambio de contraseña, que exige la contraseña actual. No acepta cambios de `role`, `is_approved`, propietarios, campos de privacidad o una contraseña enviada como `password`. El registro público tampoco permite inyectar aprobación ni campos administrativos.

`AdminUserService` filtra sus entradas mediante una lista de campos permitidos. La administración autorizada conserva la edición de rol y aprobación; los roles desconocidos se rechazan incluso al invocar directamente el servicio. Los campos de privacidad quedan fuera de esa operación.

`OlderAdultService` filtra los campos de negocio y las asignaciones administrativas autorizadas. `created_by` se obtiene del actor al crear y no se puede sustituir al editar. Las asignaciones de medicamentos anidadas se buscan dentro del adulto editado: un ID perteneciente a otro adulto devuelve 404 y revierte la transacción. La actualización del nombre familiar al editar el perfil solo afecta adultos vinculados por `family_caregiver_id`, nunca coincidencias de nombre.

Las claves adicionales no permitidas se ignoran; no se convierten automáticamente en errores de validación. Las pruebas también intentan inyectar propietarios, estados administrativos e identificadores en notas, rutinas, incidentes, horarios, vacaciones y tomas de medicamentos, y comprueban que los valores persistidos proceden del servidor y de operaciones autorizadas.

## SCRUM-788: revocar credenciales

`UserObserver`, registrado en `AppServiceProvider`, llama a `CredentialRevocationService` cuando cambia el rol o la contraseña, se retira la aprobación o se elimina un usuario. Se eliminan todos sus tokens Sanctum, sesiones de base de datos y tokens de restablecimiento de contraseña. Si el correo cambia junto con la contraseña, se limpian los tokens de restablecimiento del correo anterior y del nuevo.

La suspensión utiliza el estado existente `is_approved = false`; no se agregó otro campo de estado. Los tres roles necesitan aprobación para iniciar sesión y acceder a sus módulos, incluido `admin`. La edición administrativa permite retirar la aprobación a un administrador. Volver a aprobar una cuenta no recupera sus tokens anteriores. Cambiar datos ordinarios de perfil no revoca credenciales.

Las actualizaciones administrativas y del perfil, y la eliminación administrativa, se ejecutan en transacciones: si la operación falla, los cambios y la revocación se revierten juntos. El observador también cubre actualizaciones y eliminaciones directas de instancias Eloquent. Las actualizaciones masivas mediante Query Builder o SQL no disparan eventos del modelo; deben pasar por los servicios controlados o ejecutar explícitamente la revocación. No se afirma cobertura de escrituras SQL externas en este bloque.

## SCRUM-789: evitar revelar recursos ajenos

Las Policies devuelven `denyAsNotFound()` para recursos fuera de la propiedad o asignación permitida. Las búsquedas por adulto en servicios de rutinas, incidentes y acceso de cuidadores también normalizan los identificadores ajenos e inexistentes. El renderizador existente devuelve el mismo cuerpo JSON: `{"message":"Recurso no encontrado."}`.

| Situación | Respuesta |
| --- | --- |
| Sin credenciales válidas o con token revocado | 401 |
| Rol sin permiso para el módulo | 403, antes de enlazar el recurso |
| Recurso ajeno o inexistente dentro del módulo permitido | 404 con el mismo mensaje genérico |
| Campos de entrada inválidos | 422 según la validación del endpoint |

Se cubren identificadores enviados en ruta, filtros y cuerpo. Las colecciones conservan el filtrado por asignación. Un administrador aprobado mantiene su acceso global a los recursos administrativos: no se impone una restricción de propietario donde su rol autoriza esa operación.

## SCRUM-790: pruebas de IDOR y escalamiento

Se agregaron `ProtectedFieldsAndCredentialsTest` e `IdorAndPrivilegeEscalationTest`, con tokens Bearer reales. Se amplió además `ResourcePolicyAccessTest` para rechazar administradores sin aprobación y se actualizaron las expectativas de pruebas previas al contrato 401/404.

| Cobertura | Comprobaciones |
| --- | --- |
| Profesional, familiar y sus dos alias | Recursos asignados frente a ajenos e inexistentes; rutas, query y cuerpo; sin escrituras en recursos ajenos |
| Administrador y cuidadores | Escalamiento entre módulos; rol y aprobación; campos protegidos; operaciones administrativas legítimas |
| Revocación | Cambios entre roles, retiro de aprobación en los tres roles, contraseña, eliminación, preservación de credenciales de otros usuarios y rollback |
| Integridad | Propietarios derivados del actor, creador inmutable, sincronización familiar por ID y medicamentos anidados sin cruces entre adultos |

Desde `backend`:

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/ProtectedFieldsAndCredentialsTest.php tests/Feature/IdorAndPrivilegeEscalationTest.php --colors=never
php vendor/phpunit/phpunit/phpunit --colors=never
```

Resultado: **30 pruebas en los dos archivos nuevos y 581 aserciones, sin fallos**. Suite completa: **254 pruebas y 3374 aserciones, sin fallos**. Pint aplicó el formato del proyecto a los 30 archivos PHP de esta entrega. Los ejecutables se ejecutaron fuera del aislamiento debido a las restricciones locales de lectura/escritura en Windows.

El entorno instalado utiliza PHP 8.2.12, Laravel 11.51 y PHPUnit 11.5.55; `composer.json` declara PHP 8.3+, Laravel 13 y PHPUnit 12. No se modificaron dependencias. Queda pendiente validar en las versiones declaradas.

Las subtareas SCRUM-791 a SCRUM-796 quedan para los siguientes bloques.
