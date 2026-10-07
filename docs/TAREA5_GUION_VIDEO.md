# Guion de grabación - Tarea 5 (7 minutos)

Participantes: Luis Lee (241203), Belen Monterroso (231497), Sebastian Lemus (241155), Luis Hernandez (241424).

Preparación: abrir el PDF, las dos clases de pruebas, el workflow y una terminal en la raíz del proyecto. En GitHub, abrir Actions y los artefactos de la ejecución de esta tarea. Aumentar el tamaño de fuente; ocultar credenciales. El video y su enlace son el material pendiente de entrega.

Los enlaces de las ejecuciones verde, roja y verde están en `docs/TAREA5_EVIDENCIA.md`. Abrirlos antes de grabar permite mostrar los resultados remotos sin esperar nuevas instalaciones durante el video.

| Tiempo | Responsable sugerido | Mostrar y explicar |
|---|---|---|
| 0:00-0:50 | Luis Lee | Organízate, Laravel/PHP, frontend JS y PostgreSQL. Objetivo: verificar interacción real y preservar reglas. Comparar PHPUnit, Vitest y Playwright; justificar PHPUnit + Vitest. |
| 0:50-1:30 | Luis Lee | Comparación GitHub Actions, GitLab CI y Jenkins. Selección por repositorio alojado en GitHub, eventos push/PR, preparación declarativa y artefactos. |
| 1:30-2:30 | Belen Monterroso | `IntegrationTest.php`: I01 login-token-perfil; I02 adulto-medicamento-adquisición; I03 nota-profesional-adulto. Mostrar peticiones y `assertDatabaseHas`. SQLite real y migraciones; no es un mock ni PostgreSQL. |
| 2:30-3:20 | Sebastian Lemus | `RegressionTest.php`: horarios, rol y aprobación. Ejecutar `node tests/automation/run.mjs baseline`; mostrar 6/6 y código 0. |
| 3:20-4:20 | Sebastian Lemus | Ejecutar `node tests/automation/run.mjs regression`. Abrir `reports/mutation.diff`. Se retiró `after:start_time` en una copia de la clase real. Mostrar R01 en rojo: esperaba 422 y recibió 201; 5/6 aprobadas, código 1. |
| 4:20-5:00 | Luis Hernandez | Ejecutar `node tests/automation/run.mjs corrected`. Mostrar 6/6 y código 0. La clase original vuelve a cargarse. Mostrar hashes iguales y XML/reportes. |
| 5:00-6:15 | Luis Hernandez | GitHub Actions: abrir ejecución de push, steps de preparación, experimento, suite backend y frontend. Abrir logs de baseline, regression y corrected en el mismo experimento y artefactos JUnit. Explicar que la regresión controlada es esperada; cualquier fallo inesperado deja el job rojo. Si están disponibles ejecuciones manuales independientes, mostrar baseline verde, regression roja y corrected verde. |
| 6:15-7:00 | Todos | Resultados verificables, problemas y solución. Límites: no mide carga, navegador real ni diferencias con PostgreSQL. Mantener herramientas y ampliar posteriormente con E2E/PostgreSQL. Cerrar mencionando código, workflow y PDF. |

Comandos de preparación y reproducción en `tests/automation/README.md`. No utilizar cifras del informe de la Tarea 3: esta entrega tiene seis casos nuevos y evidencia actualizada.
