# Pruebas no funcionales del backend

Esta suite contiene verificaciones de atributos de calidad que no describen un flujo funcional aislado, como confiabilidad, idempotencia, resiliencia y seguridad.

## Ejecucion

Desde la raiz del proyecto, usando Docker Desktop y PHP 8.3 de la imagen:

```powershell
docker compose run --rm --no-deps --build -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= --entrypoint php backend artisan test --testsuite=NonFunctional
```

La primera ejecucion puede tardar mientras Docker construye la imagen. Las siguientes reutilizan sus capas.

Si PHP 8.3 o superior esta instalado localmente, tambien se puede ejecutar desde `backend`:

```powershell
php artisan test --testsuite=NonFunctional
```

Las pruebas funcionales pueden ejecutarse de forma independiente con el mismo formato:

```powershell
docker compose run --rm --no-deps --build -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= --entrypoint php backend artisan test --testsuite=Feature
```

Las pruebas especializadas que necesitan herramientas externas se mantienen separadas en la raiz del proyecto:

- `tests/performance`: carga, estres y picos con k6.
- `tests/security`: analisis de seguridad con OWASP ZAP.

## Organizacion

- `Reliability`: reintentos, concurrencia, idempotencia y recuperacion ante fallos.
- `Security`: aislamiento de datos, autorizacion y ausencia de informacion sensible.
- `Accessibility`: navegacion por teclado, zoom y tecnologias de asistencia.

## Errores del servidor (SCRUM-1018)

`Reliability/ApiServerErrorResponseTest.php` provoca fallos controlados dentro de
PHPUnit e inspecciona el contrato HTTP 500 con distintos tipos de error,
configuraciones de debug y encabezados Accept. Incluye el endpoint real de
incidentes con un servicio simulado que falla y comprueba que los datos previos
se conserven. El procedimiento y el alcance están en
[SCRUM-1020/1021](../../../docs/ERRORES_API_SCRUM_1018.md).

`Security/ApiErrorLogPrivacyTest.php` completa SCRUM-1022/1023: inspecciona
archivos temporales reales del log operativo y la auditoría firmada ante errores
API. Comprueba correlación y ausencia de marcadores sintéticos de datos clínicos,
credenciales, SQL y trazas en respuestas y registros. Los archivos de cada ensayo
se eliminan al finalizar; no utiliza registros históricos de usuarios.
