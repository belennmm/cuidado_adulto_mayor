# Pruebas de carga y estrés

Este directorio contiene pruebas de rendimiento de solo lectura para la API de Organízate. Los escenarios simulan aproximadamente 20% de administradores, 50% de cuidadores profesionales y 30% de familiares.

## Seguridad y alcance

- Ejecutar únicamente contra un ambiente local o de pruebas autorizado.
- Los escenarios incluidos no crean, actualizan ni eliminan información.
- El ejecutor crea o actualiza tres cuentas exclusivas `@performance.test` mediante `PerformanceTestSeeder`.
- El sembrador se niega a ejecutarse si Laravel está configurado como producción.
- El ejecutor elimina `setup_data` del JSON final para que los tokens temporales de sesión no queden almacenados.
- No ejecutar estrés contra producción.

## Requisitos

- Docker Desktop iniciado.
- Proyecto levantado desde la raíz con `docker compose up -d --build`.
- API disponible en `http://localhost:8080/api/ping`.

No es necesario instalar k6: el ejecutor usa la imagen oficial `grafana/k6`.

## Ejecución

Desde la raíz del proyecto:

```powershell
.\tests\performance\run.ps1 -Test smoke
.\tests\performance\run.ps1 -Test load
.\tests\performance\run.ps1 -Test stress
.\tests\performance\run.ps1 -Test spike
```

Ejecutar siempre en ese orden. Si smoke falla, no continuar con las pruebas mayores.

Para una API expuesta en otro puerto o equipo de pruebas:

```powershell
.\tests\performance\run.ps1 -Test smoke -BaseUrl "http://host.docker.internal:8080/api"
```

También se pueden reemplazar las credenciales mediante variables de entorno de k6: `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `PROFESSIONAL_EMAIL`, `PROFESSIONAL_PASSWORD`, `FAMILY_EMAIL` y `FAMILY_PASSWORD`.

## Perfiles

| Archivo | Perfil |
|---|---|
| `smoke.js` | 1 usuario, 3 iteraciones; valida scripts, acceso y datos base |
| `load.js` | aumenta hasta 20 usuarios y sostiene la carga normal |
| `stress.js` | aumenta gradualmente hasta 150 usuarios para encontrar degradación |
| `spike.js` | salta rápidamente de 5 a 100 usuarios y mide recuperación |

## Criterios iniciales

- Menos de 1% de solicitudes HTTP fallidas.
- Más de 99% de verificaciones correctas.
- Percentil 95 menor a 800 ms.
- Percentil 99 menor a 1500 ms.

Estos valores son una línea base inicial. Deben revisarse después de la primera medición y relacionarse con los requisitos reales del sistema.

## Evidencia

Cada ejecución guarda un archivo JSON fechado en `results/`. Durante carga y estrés también conviene ejecutar `docker stats` en otra terminal y guardar una captura de CPU y memoria. El informe debe incluir ambiente, fecha, escenario, usuarios, solicitudes por segundo, latencia promedio, p90, p95, p99, tasa de errores, punto de degradación y acciones de mejora.
