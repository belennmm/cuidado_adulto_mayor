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
