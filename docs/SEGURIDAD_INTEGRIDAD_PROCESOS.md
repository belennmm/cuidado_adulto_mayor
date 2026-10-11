# Seguridad e integridad de procesos

## Alcance y arquitectura

La interfaz es HTML/JavaScript servida por Nginx. `/api/` se reenvía al backend Laravel
con `proxy_pass http://backend:8080;`. Laravel usa Sanctum, middleware de acceso actual,
roles, capacidades de tokens y policies. Los Form Requests estrictos validan los campos;
los servicios aplican las operaciones y Eloquent persiste en la base de datos.
Las pruebas Laravel utilizan SQLite en memoria; eso no prueba bloqueos concurrentes
del motor de producción. Este documento complementa [las entradas de la API](INVENTARIO_ENTRADAS_A03.md),
[los permisos vigentes](PERMISOS_VIGENTES_MINIMOS_PRIVILEGIOS.md) y
[la respuesta de seguridad y respaldos](SECURITY_RESPONSE.md).

## Modelo de amenazas

Activos principales: expedientes y relaciones de cuidadores, historial de administraciones,
inventario/adquisiciones, incidentes, rutinas/notas, turnos/vacaciones, cuentas y credenciales.
Se consideran un visitante no autenticado, un usuario pendiente y un usuario autenticado
que intente acceder a otra persona, alterar un rol, saltar validaciones, repetir comandos,
usar identificadores ajenos o provocar solicitudes concurrentes. También se consideran
fallos de persistencia que dejen una operación parcialmente aplicada.

Entradas: registro/login, perfil, endpoints administrativos y de cuidadores, identificadores
de rutas, filtros de lectura y cuerpos JSON/formulario. Los listados conservan filtros de
propiedad y paginación del servidor. Los límites de solicitudes y campos siguen vigentes.
Los selectores consumen páginas internamente; sus opciones no constituyen autorización.

Límites de confianza:

- Navegador → Nginx/API: valores de campos, almacenamiento del navegador, roles mostrados,
  IDs y estados enviados son manipulables. `AuthSession` usa `localStorage` y `sessionStorage`;
  una inyección de scripts podría acceder a tokens. CSP y salida segura siguen siendo necesarias.
- Token → cuenta actual: el rol/aprobación y la huella de seguridad actuales deben coincidir;
  el servidor revoca credenciales cuando corresponde. Un token antiguo no habilita permisos anteriores.
- Request/policy → servicio: solo se aceptan campos declarados y recursos autorizados;
  comprobar un estado antes de escribir no sustituye leerlo bajo bloqueo cuando depende de otra decisión.
- Servicio → base de datos: las transacciones agrupan las escrituras relacionadas, las FK e
  índices únicos mantienen referencias/unicidad y `lockForUpdate()` serializa cambios al mismo recurso.
- Base de datos → respaldo/operación: copiar un respaldo también copia información sensible.
  Su cifrado, custodia, retención y restauración requieren controles operativos externos.

## Clasificación de datos

| Clase | Ejemplos reales | Tratamiento |
| --- | --- | --- |
| Personales | Nombres, correo, teléfono, nacimiento, habitación, contactos de emergencia, relaciones de cuidadores | Acceso por rol/asignación; evitar exportaciones y registros innecesarios. |
| Médicos | Alergias, historia clínica, estado del adulto, medicamentos/dosis, administraciones, incidentes y notas | Datos sensibles del expediente; preservar vínculos del historial y minimizar exposición. |
| Administrativos | Usuarios/roles/aprobación, turnos, vacaciones, inventario/adquisiciones y decisiones de revisión | Escrituras autorizadas; decisiones y efectos deben ser consistentes. |
| Credenciales | Contraseñas/hashes, tokens, sesiones, enlaces de restablecimiento, APP_KEY y secretos de servicio | Nunca incluirlos en respuestas, documentación de incidentes o respaldos de demostración; revocar/rotar según el procedimiento existente. |

Los textos libres pueden contener datos personales o médicos: no se consideran públicos.

## Estados y reglas de flujo

| Proceso | Reglas ejecutables |
| --- | --- |
| Cuentas | Registro público crea cuenta pendiente (`is_approved=false`); creación administrativa crea aprobada. Administrador puede aprobar, cambiar rol/aprobación por la edición existente o eliminar. Rechazo solo para pendiente no administrativa; se verifica bajo bloqueo. Repetir aprobación de una cuenta aprobada no vuelve a escribir. No se añade un estado archivado ni se elimina la edición administrativa existente. |
| Incidentes | Creación fija `abierto`. Avances permitidos: abierto → en_progreso/resuelto/cerrado; en_progreso → resuelto/cerrado; resuelto → cerrado. Conservar estado es válido. Cerrado no reabre; ningún estado retrocede. Los estados intermedios son opcionales y las notas/severidad siguen siendo editables por el profesional autorizado. El mapa está en `Incident::STATUS_TRANSITIONS`. |
| Administración programada | Solo profesional aprobado/asignado; para un registro nuevo, asignación activa, fecha dentro de inicio/fin (inclusive) y día programado. Lista de días vacía mantiene la regla existente de todos los días. Fecha del registro proviene del servidor. Reintento de una administración del mismo día devuelve el original sin alterar notas/hora ni duplicarlo, incluso si después se desactivó la asignación. |
| Inventario | Estados de disponibilidad se derivan de vencimiento, cantidad y mínimo; no se escriben como un estado libre. Se mantienen stock consolidado, sin asignar e individual separados. Ajustes de stock ya usan bloqueo/transacción y no permiten cantidad negativa; incremento y adquisición se guardan juntos. No se añade consumo automático ni reglas de dosificación. |
| Edición de expediente | Si omite `medications`, conserva las asignaciones. Si envía la lista (también vacía/null), intenta sincronizarla: toda eliminación con administraciones genera 422 y revierte expediente, asignaciones, catálogo y adquisiciones de esa actualización. Se reutiliza el control de eliminación del inventario. IDs repetidos se rechazan; IDs ajenos no se aceptan. No se implementa desactivación/archivo. |
| Vacaciones | Creación fija pending. Solo pending → approved/rejected por administrador. Estado, revisor y fecha se escriben juntos; no se puede revisar de nuevo ni cambiar una decisión con una instancia antigua. |
| Cambio de turno | Sin solicitud → pending → aprobación/rechazo, que limpia los campos de solicitud. Aprobar aplica el horario y limpia la solicitud en una sola operación. No admite revisión sin solicitud ni repetir decisión. Una pendiente no puede reemplazarse mediante otro pedido, edición o upsert del horario. Crear/editar turnos sin petición pendiente mantiene los permisos existentes; no se inventa aprobación obligatoria para esos flujos. |
| Rutinas | Solo se completa una actividad existente; completar todas determina el estado final en el servidor. No hay orden obligatorio entre actividades. Completar otra vez conserva la marca original. Bloqueo evita perder actividades completadas por solicitudes concurrentes. Editar la definición conserva el comportamiento existente: reinicia la ejecución. |

Las operaciones se dirigen a un recurso por ID. No se agregan endpoints masivos.
Los estados históricos de incidentes `pendiente` y `en seguimiento` se interpretan como
`abierto` y `en_progreso` al validar transiciones. Editar una nota no los renombra; una
transición solicitada escribe el estado canónico. No se ejecuta una migración de datos.
La única sincronización de múltiples asignaciones revisada aquí conserva el máximo existente
de 100 elementos, exige propiedad y unicidad de IDs y revierte toda la transacción si una
eliminación está protegida. Cambios de credenciales mantienen el observer de revocación existente.

## Consistencia y errores

Se lee el estado actual bajo bloqueo en revisión de cuentas/vacaciones/turnos, incidentes,
ejecución/edición de rutinas y eliminación/administración de asignaciones. La edición del
expediente y la administración adquieren primero el adulto y después la asignación para
coordinar la eliminación con el registro del historial. Una eliminación aislada también
bloquea la asignación antes de comprobar administraciones.

Los errores de flujo/validación usan 422; permisos y ocultación de recursos mantienen las
policies actuales. Los errores de persistencia conservan la respuesta genérica 500 existente,
sin mensajes SQL ni detalles internos. Un fallo de transacción no garantiza éxito: el cliente
debe mostrar el error. Los ajustes de stock no tienen clave de idempotencia y un reintento de
un aumento exitoso sigue siendo otro aumento; los límites de la Etapa 2 no sustituyen esa garantía.

## Retención y eliminación segura

No existe un plazo único ni un trabajo automático de retención definido en el proyecto.
Los responsables deben establecer plazos por clasificación, finalidad y ambiente antes de
programar purgas; este cambio no inventa plazos ni elimina datos automáticamente.

Eliminar una asignación con administraciones está bloqueado, tanto en inventario como en
la edición del expediente. La eliminación completa de adultos/usuarios conserva el comportamiento
de sus FK existentes (cascadas, referencias nulas o restricciones); este control no convierte
esa eliminación en archivado ni garantiza retención del expediente completo.
Revisar dependencias y respaldos antes de una purga operativa autorizada.

Para respaldos, seguir `SECURITY_RESPONSE.md`: cifrado con claves custodiadas fuera del repositorio,
acceso restringido, retención definida por responsables, eliminación verificable y pruebas de
restauración con datos sintéticos. Borrar una fila o un archivo no demuestra borrado físico
de copias, discos, snapshots o respaldos. El incidente del respaldo histórico documentado allí
mantiene sus acciones operativas pendientes hasta que sus responsables confirmen su cierre.

## Definition of Done de seguridad

- Revisar activos, entradas, roles/propiedad y campos de estado afectados por cada cambio.
- Definir transiciones válidas sin inferir procesos de aprobación inexistentes.
- Comprobar validación, reintentos, escrituras con estado obsoleto y rollback cuando hay varios efectos.
- Agregar/revisar pruebas negativas de IDs, campos protegidos, estados inválidos y fallos parciales.
- Ejecutar pruebas y revisión de seguridad antes de considerar el cambio verificado; registrar resultados reales.
- Conservar límites/paginación backend, CSP, controles de acceso y separación del inventario; no incluir secretos/datos reales en fixtures.
- Documentar cambios de contrato, riesgos residuales y decisiones operativas de retención.

## Pruebas añadidas y verificación pendiente

`ProfileMedicationIntegrityTest`: rechazo de eliminación con historial, rollback tardío,
preservación del vínculo, edición permitida, eliminación sin historial, IDs repetidos/ajenos.
`ProcessStateIntegrityTest`: cuentas y decisiones repetidas con modelos obsoletos, incidentes,
programación de administraciones, fallo parcial de adquisición, solicitudes de turnos y rutinas.
Se mantienen las pruebas existentes de permisos, revocación, idempotencia, payloads y límites.
Estas pruebas nuevas no han sido ejecutadas en esta intervención.

Desde la raíz del proyecto, con el código actualizado disponible en el contenedor backend:

```powershell
docker compose exec backend php artisan test --filter='ProfileMedicationIntegrityTest|ProcessStateIntegrityTest'
docker compose exec backend php artisan test
```

La persona responsable debe comprobar además solicitudes concurrentes sobre el motor de
producción en un ambiente de pruebas: SQLite en memoria no verifica `FOR UPDATE`.
No se ejecutaron Docker, pruebas, Composer, migraciones ni operaciones Git para este cambio.
