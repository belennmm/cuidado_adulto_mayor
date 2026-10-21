# SCRUM-1013: recuperar información mediante un respaldo

## Entregas para commits manuales

| Bloque | Subtareas | Alcance |
|---|---|---|
| 1 | SCRUM-1014 Generar un respaldo; SCRUM-1015 Intentar consultar sus incidentes | Respaldo cifrado y consulta de referencia anterior a la restauración. |
| 2 | SCRUM-1016 Restaurarlo en un ambiente de prueba; SCRUM-1017 Comparar cantidades de registros | Scripts de restauración local y comparación de todas las tablas; prueba PostgreSQL sintética. Ver evidencia y límites abajo. |
| 3 | SCRUM-1019 Validar usuarios, medicamentos, rutinas, incidentes y sus relaciones | Validador de contenido y relaciones PostgreSQL, más prueba de modelos/API Laravel sobre copia SQLite recuperada. Ver evidencia y límites abajo. |

La historia tiene cinco subtareas: el último bloque contiene una. Los commits y todas las operaciones de Git quedan a cargo del usuario.

Mensaje sugerido para el primer commit: `SCRUM-1014 SCRUM-1015: asegurar respaldo cifrado y consulta base de incidentes`.

## Bloque 1: generación del respaldo

`tools/backup-encrypted.ps1` requiere PowerShell 7.4 o superior, `pg_dump` compatible con el servidor PostgreSQL y `age` en PATH. Utiliza formato custom, transmite bytes directamente al cifrador y comprueba la salida de ambos procesos. Publica el archivo final únicamente si ambos terminan correctamente; elimina su parcial al fallar. Rechaza sobrescrituras y destinos dentro del repositorio. Imprime SHA256 para registrar la integridad del archivo cifrado.

Usar una base de prueba con datos sintéticos y una cuenta autorizada para leer todas las tablas que se necesitan recuperar. Preparar autenticación mediante un archivo de contraseñas libpq con permisos restringidos y, opcionalmente, un servicio libpq. El siguiente ejemplo supone un servicio `cuidado_backup_test` configurado externamente; no contiene una URI con contraseña:

```powershell
$backupFile = Join-Path $env:USERPROFILE 'RespaldosCuidado/cuidado-prueba.age'
./tools/backup-encrypted.ps1 `
  -DatabaseUrl 'service=cuidado_backup_test' `
  -AgeRecipient '<CLAVE_PUBLICA_AGE>' `
  -Destination $backupFile
Get-FileHash -LiteralPath $backupFile -Algorithm SHA256
```

Custodiar la clave privada fuera del repositorio y del servidor respaldado. Elegir un nombre nuevo por ejecución. No adjuntar el respaldo ni claves a incidencias o commits. `.gitignore` excluye `.age` y `.age.partial` como defensa adicional. El script respalda PostgreSQL; archivos de `storage`, configuración y claves de Laravel necesitan custodia independiente para una recuperación completa del servicio.

Para comparar posteriormente contra una referencia estable, detener las escrituras de la aplicación de prueba durante la captura de la referencia y el respaldo. Aunque `pg_dump` obtiene una copia consistente, las consultas hechas antes o después pueden observar cambios concurrentes.

## Bloque 1: consulta de incidentes

Con una sesión administrativa aprobada, consultar `GET /api/incidents?date=AAAA-MM-DD` para una fecha conocida. El endpoint sin fecha filtra por el día actual; un resultado vacío no demuestra pérdida de datos. Revisar todas las páginas si la respuesta está paginada. Conservar únicamente fecha, estado HTTP y conteos en la evidencia compartida, sin nombres ni detalles clínicos.

`BackupIncidentBaselineTest` crea datos sintéticos en SQLite en memoria y comprueba:

- El administrador consulta incidentes de dos cuidadores para una fecha explícita; excluye otra fecha y la consulta conserva los tres registros originales.
- El profesional sólo consulta incidentes de sus adultos asignados.
- Una consulta sin autenticación recibe HTTP 401.

Esta consulta es la referencia previa a recuperar, no una prueba de consulta del archivo cifrado. Un archivo de respaldo se consulta después de restaurarlo en PostgreSQL aislado, previsto en el bloque 2.

## Verificación del bloque 1

Desde la raíz, en Windows con el compilador C# de .NET Framework disponible:

```powershell
./tests/backup/test-backup-encrypted.ps1
```

La prueba compila ejecutables simulados en un directorio temporal único y restaura PATH al terminar. Comprueba transporte binario, fallos independientes de dump/cifrado, limpieza de parciales, rechazo de sobrescritura, extensión y destino externo. Los simuladores **no cifran**: esta prueba no valida criptografía ni demuestra que un archivo pueda restaurarse.

Desde `backend`:

```powershell
php vendor/phpunit/phpunit/phpunit --filter BackupIncidentBaselineTest
```

Resultados locales del bloque 1: prueba del script correcta; PHPUnit correcto, 3 pruebas y 8 aserciones. Fue necesario ejecutar fuera del sandbox por restricciones de lectura de PHP y movimiento de archivos temporales. En ese bloque no se generó un respaldo real ni se ejecutó una restauración por faltar `age`. SCRUM-1014 necesita esa evidencia operativa antes de darse por cerrada. La evidencia posterior del bloque 2 se registra por separado abajo.

## Bloque 2: restaurar en un ambiente de prueba (SCRUM-1016)

Mensaje sugerido para el segundo commit: `SCRUM-1016 SCRUM-1017: restaurar en pruebas y comparar cantidades de registros`.

`tools/restore-test-backup.ps1` recibe el archivo `.age`, la identidad privada custodiada externamente y su SHA256 esperado, obtenido al generar el respaldo y conservado por separado. Antes de restaurar comprueba el hash y que no haya tablas, secuencias o vistas de usuario en el destino. Sólo acepta loopback (`127.0.0.1`, `localhost`, `::1`) y nombres con prefijo `cuidado_restore_test_`. El destino debe existir, con conexión y permisos configurados mediante libpq. El script no crea ni elimina bases y no utiliza `--clean`.

Usar una instancia PostgreSQL dedicada a pruebas, sin acceso a producción y sin la aplicación escribiendo o ejecutando migraciones. Las restricciones de nombre y host evitan errores habituales de selección; también hay que comprobar a qué instancia corresponde el puerto. Restaurar con una cuenta temporal limitada al destino y un respaldo de confianza con datos sintéticos.

El contenido descifrado viaja como bytes desde `age` a `pg_restore`, sin un archivo temporal en claro. `pg_restore` utiliza una transacción y detiene la carga ante errores SQL; se comprueban además ambos códigos de salida. Un fallo del descifrador hace fallar el script. Si entrega un archivo completo antes de fallar, PostgreSQL puede haber confirmado su transacción: revisar el destino y usar otra base de prueba vacía para repetir. El script nunca promete que un hash correcto demuestre autenticidad del origen.

Ejemplo para una base vacía preparada externamente, con `age`, `psql` y `pg_restore` en PATH:

```powershell
$connection = @{ Server = '127.0.0.1'; Port = 55432; Username = 'recovery_operator' }
$backupFile = Join-Path $env:USERPROFILE 'RespaldosCuidado/cuidado-prueba.age'
$identityFile = Join-Path $env:USERPROFILE 'ClavesCuidado/identidad-age.txt'
./tools/restore-test-backup.ps1 @connection `
  -DatabaseName cuidado_restore_test_ensayo `
  -Backup $backupFile -AgeIdentity $identityFile `
  -ExpectedSha256 '<SHA256_CONSERVADO_AL_GENERAR_EL_RESPALDO>'
```

No obtener el SHA256 de un archivo dudoso como sustituto de la huella conservada al respaldar. El ejemplo no levanta la aplicación ni ejecuta sus migraciones automáticas sobre la copia restaurada.

## Bloque 2: comparar cantidades (SCRUM-1017)

`tools/capture-record-counts.ps1` captura cantidades de todas las tablas de usuario mediante una única transacción `REPEATABLE READ READ ONLY`. Usa identificadores escapados por PostgreSQL y genera JSON con esquema, tabla, cantidad y fecha UTC, sin contenidos clínicos ni credenciales. Incluye tablas vacías y esquemas distintos de `public`. En tablas particionadas, los conteos incluyen la tabla padre y las particiones según su semántica PostgreSQL; se compara cada entrada por separado, no se suman como personas distintas.

Tomar la referencia de la misma base sintética usada al respaldar, manteniendo las escrituras detenidas durante ambas operaciones. La instantánea interna del conteo no sincroniza por sí sola dos comandos distintos. Conservar los manifiestos en almacenamiento restringido; no se sobrescriben archivos existentes.

```powershell
# Antes del respaldo; base origen de PRUEBA, sin escrituras concurrentes.
./tools/capture-record-counts.ps1 @connection `
  -DatabaseName cuidado_backup_test -Output ./tmp/conteos-origen.json

# Después de restaurar, sin abrir todavía la aplicación sobre el destino.
./tools/capture-record-counts.ps1 @connection `
  -DatabaseName cuidado_restore_test_ensayo -Output ./tmp/conteos-restaurados.json

./tools/compare-record-counts.ps1 `
  -Reference ./tmp/conteos-origen.json -Restored ./tmp/conteos-restaurados.json
```

La comparación devuelve una fila por tabla con cantidades de referencia/restauración y estado `equal`, `different`, `missing` o `extra`. Si encuentra cualquier diferencia termina con error; una tabla faltante no equivale a una tabla vacía. Rechaza manifiestos vacíos, versiones incompatibles, duplicados y cantidades negativas o no enteras. Que coincidan los conteos no demuestra que coincidan los valores ni las relaciones: esa validación corresponde a SCRUM-1019.

## Prueba local del bloque 2

```powershell
./tests/backup/test-recovery.ps1
# Si PostgreSQL está instalado en otra ruta:
./tests/backup/test-recovery.ps1 -PostgresBin 'C:/ruta/PostgreSQL/bin'
```

La prueba requiere Windows, PowerShell 7.4 y las herramientas PostgreSQL (`initdb`, `pg_ctl`, `psql`, `pg_dump`, `pg_restore`). Crea un clúster temporal con contraseña aleatoria, escucha en loopback en un puerto libre y usa bases sintéticas independientes de Compose y de la aplicación. Al terminar detiene su PostgreSQL, elimina sólo su directorio temporal único y restaura las variables de entorno. Si falla el apagado conserva el directorio y avisa para revisión.

Si están disponibles `age` y `age-keygen`, genera una identidad temporal y prueba cifrado/descifrado reales. Si faltan, compila un sustituto transparente con el compilador C# de .NET Framework: el dump y la restauración PostgreSQL siguen siendo reales, pero el archivo temporal `.age` **no está cifrado** y se elimina al finalizar. El resultado identifica explícitamente el modo; ese modo no constituye evidencia del cifrado age.

El esquema sintético tiene seis tablas: usuarios (2), adultos (2), medicamentos (3), rutinas (2), incidentes (4) y una tabla vacía en un esquema con espacios y nombre con punto (0). Se comprueban coincidencia inicial, registros perdidos, tablas adicionales/faltantes, errores de hash, destino ocupado, nombre/host rechazados, archivo inválido, fallo del descifrador simulado, rollback de un archivo truncado y manifiesto duplicado. No usa las migraciones Laravel ni valida todavía las relaciones funcionales de la aplicación.

Resultado local del bloque 2: **14 comprobaciones aprobadas** con PostgreSQL 18.3. Las seis tablas conservaron sus cantidades después de restaurar el archivo custom. Los errores de archivo inválido/truncado no dejaron tablas en el destino. El clúster temporal terminó detenido y eliminado.

Se ejecutó en modo `transport-stub-no-encryption` porque no están instalados `age`/`age-keygen`. Está verificada la restauración PostgreSQL y la comparación de cantidades con datos sintéticos; queda pendiente repetir el recorrido con cifrado age real y con el respaldo de la aplicación preparado para la historia. La validación adicional de valores y relaciones se describe en el bloque 3.

## Bloque 3: validar valores y relaciones (SCRUM-1019)

Mensaje sugerido para el último commit: `SCRUM-1019: validar datos y relaciones después de recuperar el respaldo`.

`tools/validate-recovered-data.ps1` valida las nueve tablas de cuidado de `public`: `users`, `older_adults`, `medications`, `older_adult_medications`, `rutinas`, `incidents`, `routine_notes`, `medication_administrations` y `medication_acquisitions`. Requiere todas ellas y las columnas de relación actuales; un esquema incompleto termina con error. Las demás tablas siguen cubiertas por la comparación de cantidades del bloque 2.

El validador comprueba 17 relaciones explícitas contra sus registros padre, incluso si se perdió la constraint de PostgreSQL. Distingue relaciones obligatorias de referencias que admiten NULL según las migraciones: por ejemplo, inventario sin adulto asignado o incidentes históricos sin reportante. No modifica registros ni repara relaciones automáticamente.

Para detectar alteraciones sin cambiar cantidades, calcula SHA256 sobre todas las columnas de cada fila, ordenadas de forma determinista, además de metadatos de columnas y constraints. Esto incluye roles, aprobación, hashes de contraseña, dosis, horarios, actividades, notas y referencias. Normaliza la sesión a UTF-8 y UTC. El proceso consume las filas como un stream y no las imprime ni las incluye en el manifiesto; PostgreSQL realiza la ordenación, por lo que hay que dimensionar el tiempo y espacio temporal para bases grandes. Captura todo en una transacción de sólo lectura con una instantánea consistente.

El manifiesto contiene únicamente cantidades, huellas y resultados de relaciones. Custodiarlo en almacenamiento restringido junto con la referencia del ensayo, fuera de los commits: aunque no contiene registros, sus huellas se derivan de datos sensibles. La coincidencia demuestra igualdad frente a esa referencia, no que el contenido original fuera correcto. La referencia debe provenir de la misma base y ventana de respaldo, sin escrituras concurrentes, y no regenerarse desde la copia que se quiere verificar.

```powershell
# Antes de respaldar: capturar la referencia con las escrituras de prueba detenidas.
./tools/validate-recovered-data.ps1 @connection `
  -DatabaseName cuidado_backup_test -Output ./tmp/integridad-origen.json

# Después de restaurar: comparar valores, esquema y relaciones.
./tools/validate-recovered-data.ps1 @connection `
  -DatabaseName cuidado_restore_test_ensayo `
  -Reference ./tmp/integridad-origen.json -Output ./tmp/integridad-recuperada.json
```

Sin `-Reference` se captura una referencia válida y se comprueban relaciones; **no demuestra conservación del contenido**. Con `-Reference` cualquier diferencia de contenido/esquema termina con error. Un manifiesto con versión incorrecta, tablas/relaciones duplicadas o faltantes, cantidades inválidas, huellas mal formadas o relaciones inválidas también se rechaza. No se sobrescriben manifiestos ni se publica uno nuevo cuando falla la validación. La comparación presupone herramientas/esquema PostgreSQL compatibles; no sirve como comparación de representaciones entre motores o versiones con serialización distinta.

### Pruebas del bloque 3

```powershell
# Desde la raíz: dump/restauración PostgreSQL reales y validación de contenido.
./tests/backup/test-recovery.ps1 -ValidateCareData

# Desde backend: migraciones, modelos y API reales sobre una copia SQLite.
php vendor/phpunit/phpunit/phpunit --filter 'RecoveredCareDataTest|BackupIncidentBaselineTest'
```

La variante PostgreSQL utiliza el mismo clúster temporal y limpieza del bloque 2. Su fixture tiene las nueve tablas con nombres, referencias y valores sintéticos representativos; no sustituye al esquema completo Laravel. Verifica que después de restaurar coincidan contenido y esquema, acepta NULL permitidos y comprueba diez alteraciones independientes que mantienen las cantidades: contraseña, rol, medicamento, dosis, rutina, incidente, vínculo con otro adulto existente, nota, administración y adquisición. Además detecta huérfanos tras quitar una foreign key y relaciones obligatorias nulas tras quitar `NOT NULL`.

`RecoveredCareDataTest` complementa esa prueba usando las migraciones completas de Laravel en SQLite. Genera datos sintéticos, obtiene una copia real mediante `VACUUM INTO`, cambia la fuente para comprobar que la copia es independiente y conecta los modelos/API a la base recuperada. Compara íntegramente las nueve tablas, ejecuta `foreign_key_check`, verifica el hash de contraseña y los vínculos entre cuidadores, adultos, medicamentos, rutinas, incidentes, notas, administraciones y adquisiciones. Comprueba consultas administrativas, filtrado de incidentes/rutinas del profesional y HTTP 404 para un expediente ajeno al familiar. Restablece la conexión original y elimina la copia temporal al terminar. Esta prueba SQLite no representa una restauración del archivo PostgreSQL.

Evidencia local del bloque 3: **15 comprobaciones PostgreSQL aprobadas**; **4 pruebas PHPUnit y 55 aserciones aprobadas**, incluyendo la consulta de referencia del bloque 1. Se verificó texto con acentos y se fijó UTF-8 explícito en las consultas compartidas. Las pruebas se ejecutaron fuera del sandbox por las restricciones ya identificadas de procesos nativos y lectura PHP. No se ejecutaron operaciones de Git.

Los tres bloques tienen implementación y pruebas sintéticas locales. Para cerrar operativamente la historia sigue pendiente ejecutar el recorrido con `age` real y el respaldo preparado de la aplicación: conservar las referencias antes de respaldar, restaurar en la instancia aislada, comparar cantidades y validar integridad. No se ha probado una recuperación de producción ni la recuperación de archivos/configuración externos a PostgreSQL.
