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

Las versiones exactas quedan registradas en `composer.lock`, `package-lock.json` y las etiquetas de las imágenes Docker. No se permiten instalaciones de producción sin archivos lock.

## Inventario

- Backend directo: Laravel Framework, Sanctum y PHP.
- Backend de desarrollo: Faker, Pint, Tinker, Mockery, Collision y PHPUnit.
- Frontend de desarrollo y pruebas: Testing Library DOM, jsdom y Vitest.
- Imágenes: PHP/Apache, Composer, Nginx, PostgreSQL y pgAdmin.
- Las dependencias transitivas se registran en los archivos lock y en el SBOM generado por versión.

Se retiraron Laravel Sail y Pail porque no participan en el despliegue ni en las pruebas. También se eliminó el pipeline npm/Vite duplicado del backend; el frontend real se mantiene en `/frontend`.

## Controles automáticos

Cada push y pull request ejecuta:

1. `composer install` y `npm ci` para comprobar reproducibilidad.
2. `composer audit --locked --abandoned=fail`.
3. `npm audit --audit-level=high`.
4. Todas las pruebas de regresión del backend y frontend.
5. Construcción y escaneo Trivy de las imágenes, bloqueando vulnerabilidades HIGH o CRITICAL con corrección disponible.

Dependabot revisa semanalmente Composer, npm, Docker, Compose y GitHub Actions. Cada release genera un SBOM CycloneDX descargable como artefacto.

## Excepciones

Una vulnerabilidad solo puede exceptuarse en `docs/DEPENDENCY_EXCEPTIONS.md`. La excepción debe indicar identificador, componente, justificación, controles compensatorios, responsable y fecha límite. Una excepción vencida bloquea la integración hasta actualizarla o eliminarla.

## Revisión de mantenimiento

Antes de incorporar o conservar una dependencia directa se debe verificar que:

- tenga lanzamientos o actividad de mantenimiento reciente;
- disponga de repositorio y canal de avisos oficiales;
- sea compatible con las plataformas mínimas;
- no esté marcada como abandonada por Composer ni npm;
- no duplique funcionalidad ya disponible en el proyecto o la plataforma.
