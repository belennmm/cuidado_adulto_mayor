# Evidencia verificable - Tarea 5

Repositorio: [rama de entrega](https://github.com/belennmm/cuidado_adulto_mayor/tree/codex/tarea5-pruebas-automatizadas).

| Fase | Ejecución de GitHub Actions | Resultado esperado y comprobado |
|---|---|---|
| Línea base | [37558532699](https://github.com/belennmm/cuidado_adulto_mayor/actions/runs/37558532699) | Éxito: experimento completo, 186 pruebas backend, 123 frontend. |
| Regresión deliberada | [37558685367](https://github.com/belennmm/cuidado_adulto_mayor/actions/runs/37558685367) | Fallo: R01 esperaba 422 y recibió 201; cinco casos aprobados, un fallo, cero errores. |
| Recuperación | [37558775836](https://github.com/belennmm/cuidado_adulto_mayor/actions/runs/37558775836) | Workflow recuperado con fase demo; repite experimento y suites completas. |

La primera fase verde y la recuperación ejecutan `demo`: el fallo interno esperado se comprueba y se corrige antes de exigir la suite completa. Para producir un job rojo independiente se cambió temporalmente el modo predeterminado a `regression` y se publicó un commit en la rama de entrega. Después se recuperó `demo`. La clase original permanece sin cambios; la clase modificada se carga solo en el proceso de prueba mediante `auto_prepend_file`.

La evidencia descargada del servicio se encuentra en `tests/automation/reports/ci-baseline`, `ci-regression` y `ci-corrected`: metadata del run y sus jobs, logs de ejecución y artefactos JUnit. El índice `github-actions.json` vincula cada resultado a su commit y URL. Los artefactos en GitHub caducan a los 30 días; las copias versionadas preservan los resultados.

La evidencia local se conserva por separado en la raíz de `reports`: 6/6, 5/6 y 6/6 en el experimento; 186/186 backend y 123/123 frontend en la verificación amplia. La instalación local anterior utilizó PHP 8.2, Laravel 11, PHPUnit 11.5.55 y Vitest 3.2.7. CI instala los lockfiles actuales con PHP 8.3.35, Laravel 13, PHPUnit 12.5.38 y Vitest 5.0.3. Los conteos aprobados coinciden.

Grabación pendiente: seguir `TAREA5_GUION_VIDEO.md` y mostrar estas tres ejecuciones. La duración preparada es de aproximadamente siete minutos.
