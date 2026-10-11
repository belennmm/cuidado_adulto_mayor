# Política de dependencias

## Plataformas mínimas

| Componente | Versión mínima | Criterio |
|---|---:|---|
| PHP | 8.3 | Mínimo requerido por Laravel 13; debe conservar soporte de seguridad oficial. |
| Composer | 2.10.2 | Rama estable con correcciones de seguridad vigentes. |
| Laravel | 13 | Rama con soporte de seguridad hasta marzo de 2028. |
| Node.js | 22 LTS | Línea LTS usada por CI y herramientas frontend. |
| npm | 10 | Instalación reproducible mediante `npm ci`. |
| PostgreSQL | 16.15 | Rama soportada hasta noviembre de 2028. |
| Docker Engine | 26 | Evita APIs antiguas retiradas y mantiene compatibilidad con BuildKit actual. |

Las resoluciones exactas de paquetes quedan registradas en `backend/composer.lock` y
`frontend/package-lock.json`. No se permiten instalaciones de producción sin archivos lock.
Las imágenes usan etiquetas de versión; una etiqueta no es una referencia inmutable por digest
ni garantiza por sí sola un build idéntico.

## Inventario

- Backend directo: Laravel Framework, Sanctum y PHP.
- Backend de desarrollo: Faker, Pint, Tinker, Mockery, Collision y PHPUnit.
- Frontend de desarrollo y pruebas: Testing Library DOM, jsdom y Vitest.
- Imágenes: PHP/Apache, Composer, Nginx, PostgreSQL y pgAdmin.
- Las dependencias transitivas se registran en los archivos lock y en el SBOM generado por versión.

Se retiraron Laravel Sail y Pail porque no participan en el despliegue ni en las pruebas. También se eliminó el pipeline npm/Vite duplicado del backend; el frontend real se mantiene en `/frontend`.

## Controles automáticos

Cada push y pull request ejecuta:

1. Comprobar existencia de `composer.lock` y su coherencia con `composer.json` mediante
   `composer validate --no-check-publish --check-lock`, antes de `composer install`.
   `npm ci` conserva su validación del lockfile frontend y de la integridad de los paquetes descargados.
2. `composer audit --locked --abandoned=fail`.
3. `npm audit --audit-level=high`.
4. Todas las pruebas de regresión del backend y frontend.
5. Construcción y escaneo Trivy de las imágenes, bloqueando vulnerabilidades HIGH o CRITICAL con corrección disponible.

Dependabot revisa semanalmente Composer, npm, Docker, Compose y GitHub Actions. Cada release genera un SBOM CycloneDX descargable como artefacto.

## A08 — Etapa 1: integridad y reproducibilidad acotada

Se reutilizan los workflows existentes: `dependency-security.yml`,
`injection-static-analysis.yml`, `secret-scan.yml`, `tarea5-tests.yml` y `sbom.yml`.
No se cambian nombres de workflows/jobs ni se agregan auditorías duplicadas.
Las validaciones del lockfile Composer también preceden a la instalación del workflow
de integración/regresión; ambos workflows usan Composer 2.10.2.

Semgrep queda fijado a `1.180.0` en su instalación de CI, conservando las reglas locales,
los argumentos y Python 3.11 existentes. La referencia de publicación es
[Semgrep 1.180.0 en PyPI](https://pypi.org/project/semgrep/1.180.0/).
Fijar esa versión evita obtener automáticamente un analizador diferente; no bloquea
todavía todas sus dependencias transitivas ni añade un manifiesto de hashes Python.
Esta fijación no modifica dependencias de la aplicación.

### Lockfiles y límites de su garantía

- Los dos lockfiles están presentes y se conservan sin regeneración. Deben incluirse
  en los cambios que actualicen dependencias; su seguimiento en Git lo gestiona el responsable.
- El lockfile frontend usa formato 3, coincide con las dependencias directas declaradas
  y tiene valores `integrity` SHA-512 en los 110 paquetes inspeccionados.
  `npm ci` comprueba el contenido descargado contra esos valores; no se introduce otro verificador.
- `composer.lock` fija versiones y referencias de origen/distribución. Su `content-hash`
  sirve para comprobar coherencia con el manifiesto; no es el checksum de cada archivo ZIP.
  Las entradas `dist.shasum` vacías no se consideran prueba de integridad criptográfica de los ZIP.
  No se inventan hashes ni se modifica el lockfile para llenar esos campos.
- Las auditorías detectan vulnerabilidades conocidas; no prueban autenticidad del proveedor
  ni ausencia de código malicioso. Un lockfile alterado junto al manifiesto sigue necesitando revisión.

### Recursos frontend y CSP: decisión explícita

Google Fonts (`fonts.googleapis.com`/`fonts.gstatic.com`) y Boxicons 2.1.4
(`unpkg.com/boxicons@2.1.4/`) permanecen como dependencias externas de runtime.
No se descargan fuentes/iconos, no se sustituyen recursos y no se añade SRI en esta etapa,
por decisión del responsable para evitar cambios visuales sin comprobar su impacto.

Riesgo pendiente: estos recursos no tienen una línea base de integridad propia dentro
del repositorio. El contenido servido por el proveedor/CDN sigue siendo parte del límite
de confianza. Alojamiento local o SRI requieren una futura revisión controlada con procedencia,
licencias, referencias verificables y comprobación visual de las pantallas.

La CSP funcional de `frontend/nginx.conf` se conserva: estilos/fuentes de esos proveedores,
scripts y conexiones del mismo origen e iframe de YouTube permitido. No se amplían orígenes,
no se añaden `unsafe-inline`/`unsafe-eval` y se mantiene `proxy_pass http://backend:8080;`.
Esta decisión deja SCRUM-935 y cualquier reducción adicional de CSP pendientes; no se declara
que los recursos externos ya tengan integridad verificada.

### Checksums y trazabilidad

Los resúmenes de los jobs backend/frontend de seguridad registran SHA-256 de sus manifiestos
y lockfiles. El workflow de SBOM existente añade, dentro del mismo artefacto y sin cambiar
su nombre ni retención:

- `cuidado-adulto-mayor.cdx.json.sha256`: checksum del SBOM generado, calculado sobre el archivo real.
- `cuidado-integrity-inputs.sha256`: hashes de manifiestos/locks, Dockerfiles, Nginx, workflows
  y reglas Semgrep que identifican los insumos sensibles de esa ejecución.

Son registros de contenido para revisión y comparación; no son firmas ni atestaciones.
Los hashes se calculan a partir de archivos presentes en esa ejecución, no de valores inventados.
Verificar el checksum del SBOM permite detectar una modificación respecto a ese manifiesto;
si un atacante reemplaza ambos, el checksum no demuestra autenticidad.

La revisión de descargas encontró instalaciones por gestores de paquetes, imágenes base y
acciones de terceros. Los Dockerfiles no incorporan descargas manuales por curl/wget a las que
añadir un checksum independiente. Se conservan `npm ci`, las referencias de Composer y las
instalaciones del sistema operativo actuales. No se introducen descargas nuevas de recursos visuales.

### Revisión de cambios sensibles y pendientes

Cada cambio a manifiestos/locks, Dockerfiles, CSP/Nginx, workflows, configuración Dependabot
o reglas del analizador debe indicar motivo, origen/versiones y efecto sobre build/runtime;
comparar la evidencia de integridad disponible y comprobar CI antes de aprobar el despliegue.
No registrar secretos ni datos personales en resúmenes/artefactos.

No se promete reproducibilidad binaria completa: quedan pendientes imágenes por digest,
etiquetas de Actions mutables, versiones de runners/Node/Python por rama y actualizaciones
apt/apk que dependen de los repositorios actuales. Trivy mantiene su escaneo y umbral actuales.
Firmas/atestaciones y autenticidad de artefactos quedan para las etapas posteriores de A08.
No se cambia el endurecimiento del runtime, el entorno local ni `docker-compose.override.yml`.

La comprobación realizada aquí fue por lectura y análisis de archivos. No se ejecutaron
Docker, gestores de paquetes, pruebas, migraciones ni Actions. La validación con Composer,
la instalación de Semgrep fijado y la generación del checksum deben verificarse en la siguiente
ejecución autorizada de los workflows existentes, gestionada por el responsable.

Desde la raíz, el responsable puede ejecutar estas comprobaciones sin instalar paquetes:

```powershell
composer --working-dir=backend validate --no-check-publish --check-lock
Get-FileHash -Algorithm SHA256 backend/composer.json,backend/composer.lock,frontend/package.json,frontend/package-lock.json
```

En Linux, desde el directorio del artefacto SBOM descargado y extraído:

```sh
sha256sum -c cuidado-adulto-mayor.cdx.json.sha256
```

`cuidado-integrity-inputs.sha256` se compara con el código de la misma revisión que generó
el artefacto; no con otra revisión ni con un árbol que tenga modificaciones locales.

## Excepciones

Una vulnerabilidad solo puede exceptuarse en `docs/DEPENDENCY_EXCEPTIONS.md`. La excepción debe indicar identificador, componente, justificación, controles compensatorios, responsable y fecha límite. Una excepción vencida bloquea la integración hasta actualizarla o eliminarla.

## Revisión de mantenimiento

Antes de incorporar o conservar una dependencia directa se debe verificar que:

- tenga lanzamientos o actividad de mantenimiento reciente;
- disponga de repositorio y canal de avisos oficiales;
- sea compatible con las plataformas mínimas;
- no esté marcada como abandonada por Composer ni npm;
- no duplique funcionalidad ya disponible en el proyecto o la plataforma.
