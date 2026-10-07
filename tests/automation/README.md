# Tarea 5: integración y regresión

Seis pruebas nuevas en `backend/tests/Feature/Tarea5`: I01-I03 verifican integraciones reales de Laravel con SQLite; R01-R03 protegen reglas implementadas de horarios, roles y aprobación de usuarios. No usan mocks de servicios ni de persistencia. Sanctum se simula solamente en los escenarios que no estudian el inicio de sesión; I01 utiliza un token real.

## Preparación

PHP 8.2 con PDO SQLite, mbstring, DOM y XML, Composer, Node 22 y npm. Desde la raíz:

```powershell
composer install --working-dir=backend --no-interaction --prefer-dist
npm.cmd ci --prefix frontend
node tests/automation/run.mjs demo
```

El script trabaja con `backend/phpunit.xml`: `APP_ENV=testing`, SQLite `:memory:` y `RefreshDatabase`. No requiere levantar PostgreSQL ni el servidor HTTP y no usa la base de producción.

## Ejecuciones independientes

```powershell
node tests/automation/run.mjs baseline
node tests/automation/run.mjs regression
# El comando anterior debe finalizar con código 1, R01 espera 422 y recibe 201.
node tests/automation/run.mjs corrected
node tests/automation/run.mjs full
npm.cmd test --prefix frontend -- --reporter=default --reporter=junit --outputFile=../tests/automation/reports/frontend.xml
```

`demo` ejecuta baseline, regression y corrected en procesos PHP separados y comprueba exactamente un fallo de aserción sin errores, atribuible a R01. Su código final es 0 cuando el experimento se demuestra; la fase `regression` por separado devuelve 1. Un fallo inesperado interrumpe la demostración.

La mutación elimina `after:start_time` de una copia de `SaveCaregiverScheduleRequest`. `auto_prepend_file` carga esa clase modificada en el proceso de prueba, en lugar de la clase original. La petición atraviesa los controladores, servicios, Eloquent y base de datos reales. La corrección carga nuevamente la clase original. Los hashes comprueban que el archivo original conserva su contenido. No se desactivan pruebas ni se cambian sus expectativas.

## Evidencia y CI

`reports/` conserva registros, XML JUnit y manifiestos del equipo local. Los archivos de mutación se regeneran y se excluyen de Git. `.github/workflows/tarea5-tests.yml` se dispara en push y pull_request. Instala dependencias, ejecuta el experimento, la suite completa del backend y las pruebas complementarias de frontend. Los artefactos del CI se generan en un directorio independiente (`ci-reports/`) y se conservan 30 días, incluso cuando un paso falla.

`workflow_dispatch` permite elegir baseline, regression o corrected para obtener ejecuciones independientes verde, roja y verde en GitHub Actions. Si el workflow solamente existe en una rama nueva, el botón manual aparece al incorporarlo a la rama predeterminada. El push ya puede ejecutar el experimento completo desde una rama nueva.

Informe: `output/pdf/Tarea_5_Pruebas_Integracion_Regresion_Organizate.pdf`. Guion: `docs/TAREA5_GUION_VIDEO.md`. Registrar el video de 5 a 8 minutos queda pendiente.
