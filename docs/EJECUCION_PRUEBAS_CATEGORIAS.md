# Ejecución de pruebas por categorías

## Organización real

- Backend: PHPUnit organiza `Unit`, `Feature` y `NonFunctional` en `backend/phpunit.xml`. La última suite contiene `Security` y `Reliability`. Su README menciona accesibilidad, pero actualmente no existe esa carpeta: no se creó una suite vacía.
- Frontend: Vitest conserva `tests/unit` y `tests/dom`. Los comandos nuevos seleccionan archivos existentes por atributo de calidad, sin moverlos ni duplicarlos.
- Integración/regresión: `tests/automation/run.mjs` conserva su experimento independiente; no se incorpora a estos grupos porque la fase `regression` falla deliberadamente.
- Escáneres: PHP SAST, Semgrep, auditorías y ZAP siguen separados de las pruebas de comportamiento.
- Rendimiento: k6 y sus scripts en `tests/performance` y `tests/volume` necesitan infraestructura y no forman parte de los agregados locales.

## Frontend: comandos locales

Desde `frontend`, con Node/npm compatibles con `package.json` y dependencias ya instaladas:

| Comando | Qué ejecuta | Servicios externos |
|---|---|---|
| `npm test` | Suite Vitest completa existente. | No. |
| `npm run test:watch` | Modo interactivo existente. | No. |
| `npm run test:security` | URLs/CSP e inyección, salida segura, cookies/CSRF, metadatos de autenticación, MFA, sesiones, guards y listados seguros. | No. |
| `npm run test:reliability` | Errores API controlados, formularios con sesión expirada, integridad de navegación y concurrencia/errores de paginación. | No. |
| `npm run test:non-functional` | Unión de los dos grupos anteriores, ejecutando cada archivo una vez. | No. |

La lista única de selección está en `frontend/scripts/run-test-category.mjs`. Si desaparece un archivo seleccionado, el comando falla antes de ejecutar Vitest. Se conserva la salida y el código de error de Vitest; no se instala nada ni se invocan herramientas backend. Pueden pasarse argumentos de Vitest, por ejemplo `npm run test:security -- --reporter=verbose`.

### Selección exacta

Seguridad:

- `tests/unit/api-config.test.js`
- `tests/unit/auth-session.test.js`
- `tests/unit/browser-api.test.js`
- `tests/unit/safe-output-context.test.js`
- `tests/unit/url-and-injection-security.test.js`
- `tests/dom/account-mfa.test.js`
- `tests/dom/account-sessions.test.js`
- `tests/dom/protected-page-auth.test.js`
- `tests/dom/family-auth-guard.test.js`
- `tests/dom/safe-admin-lists.test.js`

Confiabilidad:

- `tests/unit/api-config.test.js`
- `tests/dom/form-session-expiration.test.js`
- `tests/dom/navigation-links.test.js`
- `tests/dom/admin-user-api-error.test.js`
- `tests/dom/admin-shifts-api-error.test.js`
- `tests/dom/older-adult-create-error.test.js`
- `tests/dom/family-dashboard-api-error.test.js`
- `tests/dom/list-pagination.test.js`

`api-config.test.js` pertenece a ambos atributos (restricciones de URL/credenciales y manejo de errores) y solo se ejecuta una vez en el agregado. Navegación comprueba también guards, y paginación incluye el comportamiento funcional necesario para probar respuestas fuera de orden. Es una selección por archivo, no una reclasificación de cada aserción. No representa toda la seguridad o confiabilidad del sistema: otras regresiones siguen dentro de `npm test` y de las suites backend.

## Backend: ejecución manual separada

Desde `backend`, con PHP compatible, dependencias de desarrollo, PDO SQLite y demás extensiones del proyecto:

```sh
composer test:non-functional
composer test:security
composer test:reliability
```

Los comandos nuevos llaman directamente a `vendor/bin/phpunit` mediante `@php`. No arrancan un servidor ni usan el entrypoint de producción. `phpunit.xml` fuerza el entorno de pruebas y SQLite en memoria; las pruebas preparan su esquema mediante Laravel. No requieren PostgreSQL operativo. No ejecutar contra una configuración de datos reales.

- No funcionales: suite `NonFunctional`, actualmente sus nueve clases de seguridad/confiabilidad.
- Seguridad: las tres clases de `tests/NonFunctional/Security` (privacidad de logs, aislamiento familiar e integridad de sesión familiar).
- Confiabilidad: las seis clases de `tests/NonFunctional/Reliability` (persistencia, disponibilidad, respuestas 500, respaldo/recuperación e idempotencia de administraciones).

Las numerosas pruebas de autorización, validación y procesos en `Feature` no se trasladan a esas carpetas. Siguen cubiertas por la suite backend completa existente. El runtime `production` instala `--no-dev`; usar un entorno de desarrollo/testing con PHPUnit disponible. No se ejecutaron comandos backend al implementar esta organización.

## Seguridad externa: requisitos explícitos

| Verificación | Comando manual y ubicación | Requisitos |
|---|---|---|
| PHP SAST | Raíz: `php tests/security/php-sast.php` | PHP; analiza fuentes sin ejecutar la aplicación. |
| Semgrep | Raíz: `semgrep scan --config tests/security/semgrep.yml --error --metrics=off --disable-version-check backend/app backend/routes frontend/js` | Semgrep, fijado en CI a 1.180.0. |
| Dependencias PHP | Backend: `composer audit --locked --abandoned=fail` | Composer y acceso a sus fuentes de auditoría. |
| Dependencias JS | Frontend: `npm audit --audit-level=high` | npm y acceso al registro. |
| ZAP | Raíz: `.\tests\security\run-zap-baseline.ps1` | PowerShell, Docker y aplicación local disponible. |

El comando `npm run test:security` no ejecuta estas herramientas ni promete sustituirlas. El escaneo autenticado puede preparar cuentas; revisar primero `tests/security/README.md`.

## Rendimiento independiente

Se conserva el runner existente, sin cambiar escenarios ni umbrales. Desde la raíz, en PowerShell y en un entorno local autorizado:

```powershell
.\tests\performance\run-k6.ps1 -Profile smoke
```

También acepta `load`, `stress` y `volume100`. Requiere Docker, backend disponible y las contraseñas `AdminPassword`, `ProfessionalPassword` y `FamilyPassword`, que PowerShell solicita al omitirlas. **Ejecuta `PerformanceTestSeeder` y prepara cuentas locales** antes de lanzar k6. No usar sobre datos reales. Los seeders necesitan `APP_ENV=local` o `testing`.

Este comando ya es independiente y corto: no se agregó un alias npm que oculte Docker o seeding. `tests/performance/run.ps1` y `tests/volume/run-k6.ps1` son runners adicionales con otros parámetros; no se unificaron ni se asumió que fueran intercambiables. Consultar `tests/performance/README.md` y `tests/security/README.md` antes de elegir un escenario. Ninguno se ejecutó durante este trabajo.

## CI y mantenimiento

Los workflows no cambian. `dependency-security.yml` ya ejecuta las suites completas frontend/backend y auditorías; `injection-static-analysis.yml` ejecuta PHP SAST y Semgrep; `tarea5-tests.yml` mantiene la integración/regresión; ZAP y k6 no se añadieron a estos flujos. Los nuevos alias son una forma de obtener resultados locales por categoría, no nuevos checks obligatorios de GitHub.

Al agregar pruebas sobre estos atributos, revisar la selección del runner. Conservar `npm test` como cobertura completa; pasar solo una categoría no demuestra que toda la suite funcione.
