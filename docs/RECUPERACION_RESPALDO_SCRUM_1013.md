# SCRUM-1013: recuperar información mediante un respaldo

## Entregas para commits manuales

| Bloque | Subtareas | Alcance |
|---|---|---|
| 1 | SCRUM-1014 Generar un respaldo; SCRUM-1015 Intentar consultar sus incidentes | Respaldo cifrado y consulta de referencia anterior a la restauración. |
| 2 | SCRUM-1016 Restaurarlo en un ambiente de prueba; SCRUM-1017 Comparar cantidades de registros | Restauración aislada y comparación de conteos. Pendiente. |
| 3 | SCRUM-1019 Validar usuarios, medicamentos, rutinas, incidentes y sus relaciones | Comprobación funcional y de relaciones después de recuperar. Pendiente. |

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

Resultados locales del bloque: prueba del script correcta; PHPUnit correcto, 3 pruebas y 8 aserciones. Fue necesario ejecutar fuera del sandbox por restricciones de lectura de PHP y movimiento de archivos temporales. El equipo no dispone de `age`: no se generó un respaldo real ni se ejecutó una restauración. SCRUM-1014 necesita esa evidencia operativa antes de darse por cerrada. Los conteos recuperados y las relaciones de SCRUM-1016/1017/1019 todavía no están verificados.
