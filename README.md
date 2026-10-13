# Organízate: Cuidado del adulto mayor

Plataforma web para organizar el cuidado de adultos mayores en casas de retiro. Integra expedientes, rutinas, medicamentos, incidentes, horarios y solicitudes de cuidadores, con acceso según el rol y las asignaciones de cada usuario.

## Funcionalidades y roles

| Rol | Funciones principales |
|---|---|
| Administrador (`admin`) | Gestionar cuentas y aprobaciones, expedientes, inventario, horarios, vacaciones, ejercicios y estadísticas. |
| Cuidador profesional (`profesional`) | Consultar adultos asignados, registrar administraciones de medicamentos e incidentes, gestionar notas y rutinas, consultar horarios y solicitar cambios/vacaciones. |
| Cuidador familiar (`familiar`) | Consultar información, incidentes y rutinas de los adultos vinculados a su cuenta, según las policies. |

La API aplica permisos por operación y registro; tener un rol no concede acceso a todos los datos. También existen recuperación de contraseña, MFA, administración de sesiones y reautenticación para operaciones sensibles.

## Tecnologías y arquitectura

- **Frontend:** HTML, CSS y JavaScript, servido por Nginx; Google Fonts y Boxicons externos.
- **Backend:** Laravel 13 y Sanctum. Composer declara PHP `^8.3`; la imagen usa PHP 8.5.10 con Apache.
- **Datos:** PostgreSQL 16 (`postgres:16-alpine`) y pgAdmin 9.18.
- **Pruebas:** PHPUnit 12, Vitest y jsdom; scripts de seguridad y de rendimiento.
- **Infraestructura:** Docker Compose y workflows de GitHub Actions.

El navegador consume `/api` desde el mismo origen del frontend. Nginx sirve los archivos estáticos y reenvía `/api/` con `proxy_pass http://backend:8080;`. Apache ejecuta Laravel en el puerto interno 8080; Laravel se conecta a `postgres:5432`. pgAdmin es una herramienta de administración separada.

El frontend no necesita un build de npm para servirse: su Dockerfile copia los archivos a Nginx. npm se utiliza para las pruebas.

## Requisitos

Para ejecutar con contenedores: Docker con Compose v2; en Windows, Docker Desktop funcionando. Los comandos siguientes se ejecutan desde la raíz del repositorio, salvo indicación contraria.

Para pruebas fuera de contenedores: PHP compatible con `backend/composer.json`, Composer y extensiones requeridas (incluidas PDO SQLite, mbstring, DOM, XML y cURL). Para frontend: Node.js 22 o superior y npm 10 o superior, según `frontend/package.json`.

## Preparar el entorno local

Copiar la plantilla de la raíz, sin sobrescribir un `.env` existente. En PowerShell:

```powershell
Copy-Item .env.example .env
```

`.env` alimenta Compose; `backend/.env.example` corresponde a Laravel y contiene opciones adicionales. Los dos archivos no son intercambiables. No publicar `.env`, claves ni contraseñas.

Configurar en el `.env` de la raíz estos valores para acceso local por HTTP:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:3000
CORS_ALLOWED_ORIGINS=http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:8080
DB_SSLMODE=prefer
SESSION_SECURE_COOKIE=false
RUN_SEEDERS=false
```

Completar `APP_KEY`, `POSTGRES_PASSWORD`, `PGADMIN_DEFAULT_EMAIL` y `PGADMIN_DEFAULT_PASSWORD` con valores propios. La plantilla trae valores de producción y dominios de ejemplo: no levantar el entorno local sin adaptarlos.

Para generar una clave Laravel de 32 bytes en PowerShell, sin requerir que el backend ya esté arrancado:

```powershell
$applicationKeyBytes = New-Object byte[] 32
$applicationKeyGenerator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$applicationKeyGenerator.GetBytes($applicationKeyBytes)
$applicationKeyGenerator.Dispose()
"base64:" + [Convert]::ToBase64String($applicationKeyBytes)
```

Guardar el resultado como `APP_KEY` en `.env`. No regenerar la clave de un entorno con datos cifrados existentes.

### Variables principales

| Variables | Uso |
|---|---|
| `APP_KEY`, `APP_URL`, `APP_ENV`, `APP_DEBUG` | Clave de Laravel, URL y entorno. Producción exige debug deshabilitado. |
| `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` | Base y cuenta de PostgreSQL; Compose las transmite al backend como variables `DB_*`. |
| `DB_SSLMODE` | `require` en la base; desarrollo permite `prefer` para el PostgreSQL local sin TLS configurado. |
| `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS` | Orígenes y dominios de autenticación autorizados. |
| `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE`, `SESSION_ENCRYPT` | Configuración de cookies/sesiones. No trasladar los ajustes HTTP locales a producción. |
| `FRONTEND_PORT`, `BACKEND_PORT`, `PGADMIN_PORT` | Puertos publicados al host; por defecto 3000, 8080 y 5050. |
| `POSTGRES_PORT` | Puerto del host solo cuando se incluye `docker-compose.dev.yml`. |
| `PGADMIN_DEFAULT_EMAIL`, `PGADMIN_DEFAULT_PASSWORD` | Cuenta inicial de pgAdmin, independiente de las cuentas de la aplicación. |
| `RUN_SEEDERS` | Control del seeding en el arranque; predeterminado `false`. |
| `MAX_REQUEST_BYTES` | Límite del backend: 2097152 bytes por defecto. |

Las opciones adicionales de sesiones, auditoría, correo, recuperación de contraseña y conexiones salientes están en `backend/.env.example` y sus documentos. El servicio Compose no transmite automáticamente todas ellas: agregar una variable al `.env` de la raíz no garantiza que llegue al contenedor.

## Construir y levantar servicios

### Configuración base y desarrollo

`docker-compose.yml` define el runtime con imágenes de producción, restricciones de contenedores y volúmenes persistentes. `docker-compose.dev.yml` ajusta Laravel para desarrollo y publica PostgreSQL al host. No monta el código fuente ni convierte el backend en una imagen de testing.

Los siguientes comandos seleccionan explícitamente base + desarrollo, sin aplicar el override local:

```sh
docker compose -f docker-compose.yml -f docker-compose.dev.yml config --quiet
docker compose -f docker-compose.yml -f docker-compose.dev.yml build backend frontend
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d postgres backend frontend pgadmin
docker compose -f docker-compose.yml -f docker-compose.dev.yml ps
```

`build` construye imágenes; `up` crea o inicia servicios. Para revisar fallos de arranque y detenerlos conservando los volúmenes:

```sh
docker compose -f docker-compose.yml -f docker-compose.dev.yml logs --tail=100 backend frontend
docker compose -f docker-compose.yml -f docker-compose.dev.yml down
```

Existe `docker-compose.override.yml` con ajustes locales de permisos y escritura. Compose lo aplica automáticamente al usar `docker compose` sin una lista explícita de archivos. Es opcional, no una configuración de producción; revisar sus efectos antes de usarlo. Si el entorno local necesita esos ajustes existentes, incluirlo explícitamente entre la base y desarrollo y mantener la misma selección en todos los comandos:

```sh
docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.dev.yml up -d --build postgres backend frontend pgadmin
```

No se recomienda eliminar volúmenes para solucionar problemas de arranque.

### Puertos y acceso

| Servicio | Puerto interno | Acceso desde el host por defecto |
|---|---|---|
| `frontend` | 8080 | http://localhost:3000 |
| `backend` | 8080 | http://localhost:8080; prueba de respuesta en `/api/ping` |
| `postgres` | 5432 | No publicado en la base; `localhost:5432` con el archivo de desarrollo. |
| `pgadmin` | 8080 | http://localhost:5050 |
| `backend-test` | No requiere servidor HTTP | Servicio del perfil `test`, para ejecuciones puntuales. |

Los puertos publicados están vinculados a `127.0.0.1`. La entrada del frontend es `index.html`, con inicio de sesión y solicitud de cuenta. No existen cuentas predefinidas si no se cargan seeders o se crean usuarios. El autorregistro sigue el flujo de aprobación; no concede administración.

### PostgreSQL y pgAdmin

Entrar a pgAdmin con los valores propios de `PGADMIN_DEFAULT_EMAIL` y `PGADMIN_DEFAULT_PASSWORD`. Registrar una conexión con host **`postgres`**, puerto **5432**, base `POSTGRES_DB`, usuario `POSTGRES_USER` y contraseña `POSTGRES_PASSWORD`. Desde pgAdmin, `localhost` identifica su propio contenedor, no PostgreSQL.

Los datos se conservan en `postgres_data`; también existen `pgadmin_data`, `backend_storage` y `backend_cache`. La configuración base no prepara certificados TLS para PostgreSQL: `DB_SSLMODE=require` necesita infraestructura compatible; el archivo de desarrollo usa `prefer`.

## Migraciones, seeders y cuentas de demostración

El entrypoint del backend ejecuta `php artisan optimize:clear` y **`php artisan migrate --force` en cada arranque**, también en producción. No hace falta agregar un paso manual de migración al arranque local descrito. Una imagen nueva puede aplicar cambios de esquema/datos y un fallo puede impedir el inicio. Separar migraciones del arranque sigue siendo una recomendación pendiente que requiere un procedimiento operativo.

La migración histórica `2026_10_08_000001_make_scheduled_medication_administrations_idempotent.php` elimina administraciones duplicadas antes de crear el índice único. Su rollback no recupera esas filas. Revisar migraciones y respaldos antes de desplegar sobre datos existentes; no usar reinicios destructivos de la base como instalación normal.

Los seeders no se ejecutan por defecto. Con `RUN_SEEDERS=true`, el entrypoint llama a `db:seed --force`; con `fresh`, lo hace si no existen usuarios (esto no demuestra que toda la base esté vacía).

`DatabaseSeeder` carga `InitialDataSeeder` y `MobilityExerciseSeeder`. Los seeders de demostración, seguridad y rendimiento usan `SeedCredentials`, que solo admite `local` o `testing`. El catálogo de ejercicios usa `updateOrCreate`; repetir seeders puede modificar registros existentes.

`InitialDataSeeder` define cuentas de los tres roles. No hay una contraseña fija universal: `DEMO_SEED_PASSWORD` permite configurar una clave válida y, si está vacía, se genera una aleatoria. La política exige al menos 15 caracteres, mayúsculas, minúsculas, números y símbolos, además de los controles de `SafePassword`. No se publican aquí correos personales ni contraseñas. Revisar el seeder en un entorno aislado para preparar las cuentas necesarias. Compose no transmite `DEMO_SEED_PASSWORD` por defecto, por lo que no basta con agregarla al `.env` de la raíz.

## Pruebas

Los comandos de esta sección son para desarrollo/testing, no para la base de producción. No se necesita PostgreSQL para la suite configurada: `backend/phpunit.xml` fuerza SQLite en memoria y contiene suites `Unit`, `Feature` y `NonFunctional`.

### Backend en contenedor

El servicio normal `backend` usa el target `production`, que instala Composer con `--no-dev`; no es el contenedor adecuado para PHPUnit. Usar `backend-test`, del perfil `test`, con el target `testing`:

```sh
docker compose -f docker-compose.yml --profile test build backend-test
docker compose -f docker-compose.yml --profile test run --rm --no-deps --volume ./tests/security:/var/www/html/tests/security:ro backend-test ./vendor/bin/phpunit
```

El comando explícito de PHPUnit sustituye el CMD de la imagen (`php artisan test`). El montaje comparte el script real del repositorio sin copiarlo ni duplicarlo: el contexto de build `backend` no incluye el directorio raíz `tests/security`. `StrictJsonAndStaticAnalysisTest` lo resuelve desde `__DIR__` con la estructura del contenedor.

Para probar únicamente ese control:

```sh
docker compose -f docker-compose.yml --profile test run --rm --no-deps --volume ./tests/security:/var/www/html/tests/security:ro backend-test ./vendor/bin/phpunit --filter=StrictJsonAndStaticAnalysisTest
```

Compose requiere que las variables obligatorias del `.env` estén completas incluso para resolver esta configuración. `backend-test` no utiliza el entrypoint de producción ni ejecuta sus migraciones de arranque; las pruebas pueden crear su esquema SQLite mediante los mecanismos de testing de Laravel.

### Backend fuera de contenedor

Desde `backend`, con el entorno de desarrollo preparado y sin sobrescribir un `.env` existente:

```sh
composer validate --no-check-publish --check-lock
composer install --no-interaction --prefer-dist
php vendor/phpunit/phpunit/phpunit -c phpunit.xml
```

La suite local encuentra el script compartido en la raíz del repositorio. CI usa también `php artisan test` después de instalar dependencias de desarrollo. No usar `composer setup` como simple instalación de pruebas: ese script genera clave y ejecuta migraciones.

### Frontend

Desde `frontend`:

```sh
npm ci
npm test
npm run test:watch
```

`npm ci` instala el lockfile; `npm test` ejecuta Vitest una vez; `test:watch` mantiene el modo interactivo. La configuración usa jsdom y `tests/setup.js`. La imagen Nginx no incluye Node/npm.

### Seguridad y otras verificaciones

Desde la raíz, con PHP disponible, el análisis estático no ejecuta la aplicación:

```sh
php tests/security/php-sast.php
```

Desde `backend`: `composer audit --locked --abandoned=fail`. Desde `frontend`: `npm audit --audit-level=high`. El workflow de análisis añade Semgrep 1.180.0 con reglas locales.

Los procedimientos OWASP ZAP y k6 están en [tests/security/README.md](tests/security/README.md). Algunos preparan cuentas o generan carga: revisar el alcance y usarlos únicamente en un entorno autorizado. El experimento de regresión y sus reportes se explican en [tests/automation/README.md](tests/automation/README.md); la fase `regression` falla deliberadamente.

## Estructura del repositorio

```text
backend/                 Laravel: app, routes, config, database y tests
frontend/                HTML, CSS, JavaScript, assets y pruebas Vitest
.github/workflows/       Auditorías, pruebas, análisis y SBOM
docs/                    Documentación funcional y de seguridad
tests/automation/        Experimento de integración/regresión y reportes
tests/security/          Análisis estático, reglas Semgrep y scripts ZAP
tests/performance/       Recursos de pruebas de rendimiento
tests/volume/            Scripts y escenarios k6
tools/                   Scripts de respaldo cifrado y escaneo de secretos
deploy/nginx/            Ejemplo de configuración para despliegue
```

La configuración de runtime y testing vive en los archivos Compose de la raíz y los Dockerfiles de backend/frontend. Los lockfiles son `backend/composer.lock` y `frontend/package-lock.json`.

## Controles de seguridad implementados

- Requests estrictos: estructura, tipos, campos permitidos, JSON duplicado/malformado y límites de payload.
- Autenticación, aprobación, roles, policies y filtros por asignación para limitar acceso a registros ajenos.
- Rate limits, paginación de listados sensibles, gestión de sesiones, MFA y reautenticación.
- Transacciones, bloqueos y validación de estados para operaciones críticas; protección del historial de administraciones de medicamentos.
- Cabeceras de seguridad y CSP en Nginx, junto con controles de URLs y renderizado del frontend.
- Auditoría de eventos, alertas y controles de conexiones salientes descritos en `/docs`.
- Auditorías de dependencias, análisis estático, escaneo de secretos y vulnerabilidades de imágenes en CI; SBOM y checksums.

Estos controles no sustituyen revisión de cambios ni configuración operativa. Los hashes del SBOM y sus entradas **no están firmados**: permiten comparar contenido, pero no autentican por sí solos su origen. Google Fonts y Boxicons siguen siendo externos y no tienen una línea base de integridad propia dentro del repositorio.

## GitHub Actions

| Workflow | Función |
|---|---|
| [dependency-security.yml](.github/workflows/dependency-security.yml) | Valida lockfile PHP, instala dependencias bloqueadas, ejecuta auditorías y suites, construye imágenes y las escanea con Trivy. |
| [injection-static-analysis.yml](.github/workflows/injection-static-analysis.yml) | Analizador PHP y reglas Semgrep para SQL, comandos y DOM. |
| [secret-scan.yml](.github/workflows/secret-scan.yml) | Detecta posibles secretos y archivos de respaldo mediante el script PowerShell existente. |
| [tarea5-tests.yml](.github/workflows/tarea5-tests.yml) | Integración, experimento de regresión, suite backend y frontend, con reportes como artefactos. |
| [sbom.yml](.github/workflows/sbom.yml) | En release publicada o ejecución manual, genera SBOM CycloneDX del repositorio, metadatos y manifiestos SHA-256. |

No hay un workflow de despliegue automático entre estos archivos. Que los checks sean obligatorios para integrar cambios depende de reglas configuradas en GitHub; su existencia no demuestra que `main` esté protegida.

## Notas operativas

- Las imágenes usan tags, no una fijación completa por digest; el SBOM del repositorio no equivale a una attestation firmada de la imagen desplegada.
- El override local cambia restricciones de contenedores. No trasladarlo a producción sin revisión.
- HTTPS, SMTP para recuperación de contraseña y TLS de base de datos requieren configuración de despliegue; el entorno HTTP local no cubre esas garantías.
- Producción instala dependencias sin herramientas de testing. No intentar resolver una prueba faltante instalando herramientas en el runtime normal.
- No se incluyen resultados actuales de pruebas ni se afirma que los comandos se hayan ejecutado al actualizar este documento.

## Documentación adicional

- [Endpoints y roles](docs/INVENTARIO_ENDPOINTS_ROLES.md): rutas y autorizaciones del backend.
- [Mínimo privilegio](docs/PERMISOS_VIGENTES_MINIMOS_PRIVILEGIOS.md): permisos vigentes por rol.
- [Policies y asignaciones](docs/POLICIES_ASIGNACION_DENEGACION.md): acceso a registros y denegación por defecto.
- [Integridad de procesos](docs/SEGURIDAD_INTEGRIDAD_PROCESOS.md): estados, transacciones, riesgos y Definition of Done.
- [Inputs y análisis estático](docs/CIERRE_A03_NORMALIZACION_JSON_ANALISIS.md): normalización, JSON y controles de inyección.
- [Sesiones y autenticación](docs/SESIONES_AUTENTICACION_A07.md): alcance de los controles de autenticación.
- [Eventos de seguridad](docs/EVENTOS_SEGURIDAD_A09.md): auditoría y trazabilidad.
- [Respuesta a alertas](docs/RESPUESTA_ALERTAS_A09.md): actuación y verificación de alertas.
- [Conexiones salientes](docs/CONEXIONES_SALIENTES_A010.md): destinos permitidos, restricciones y pruebas.
- [Excepciones de dependencias](docs/DEPENDENCY_EXCEPTIONS.md): registro de excepciones de vulnerabilidades.
