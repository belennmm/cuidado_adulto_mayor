# Pruebas de volumen con k6

La prueba genera trafico autenticado concurrente para los roles administrador, profesional y familiar. Cada usuario virtual consulta tres endpoints de lectura y espera un segundo antes de repetir el flujo.

## Perfiles

| Perfil | Usuarios virtuales por rol | Total maximo | Duracion aproximada |
|---|---:|---:|---:|
| `smoke` | 3 | 9 | 25 segundos |
| `load` | 15 | 45 | 1 minuto 50 segundos |
| `stress` | 40 | 120 | 2 minutos 30 segundos |
| `volume100` | 33-34 | 100 | 2 minutos 30 segundos |

## Umbrales de aceptacion

- Menos del 1 % de solicitudes HTTP fallidas.
- Percentil 95 menor de 1 segundo.
- Percentil 99 menor de 2 segundos.
- Mas del 99 % de verificaciones funcionales aprobadas.

## Ejecucion

Levante el ambiente local y ejecute desde la raiz:

```powershell
docker compose up -d --build
.\tests\performance\run-k6.ps1 -Profile smoke
.\tests\performance\run-k6.ps1 -Profile load
.\tests\performance\run-k6.ps1 -Profile stress
.\tests\performance\run-k6.ps1 -Profile volume100
```

El script prepara las mismas cuentas aisladas usadas por OWASP ZAP. Los archivos JSON se generan en `tests/performance/reports` y se excluyen de Git porque corresponden a evidencia de cada ejecucion.

No ejecute `load` o `stress` contra produccion sin autorizacion y monitoreo de infraestructura.
