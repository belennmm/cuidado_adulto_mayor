# Pruebas de seguridad con OWASP ZAP

Este directorio contiene el punto de partida reproducible para evaluar los requisitos no funcionales de seguridad del sistema.

## Alcance

| ID | Requisito | Validacion |
|---|---|---|
| RNF-16 | Las contrasenas deben almacenarse de forma segura. | Prueba automatizada del hash y revision de la base de datos. |
| RNF-17 | El acceso debe bloquearse despues de 5 intentos fallidos. | Pruebas de `AuthenticationTest`. |
| RNF-18 | Solo usuarios autorizados pueden acceder a datos medicos. | Pruebas de autorizacion y aislamiento por rol. |
| RNF-23 | Debe mostrarse el aviso de privacidad antes del registro. | Aviso visible y politica enlazada en el formulario. |
| RNF-24 | El consentimiento debe almacenarse para todos los usuarios. | Validacion obligatoria y almacenamiento de fecha/version para nuevos autorregistros. |
| RNF-42 | Las APIs internas deben ser seguras. | Escaneo pasivo de OWASP ZAP y pruebas de autenticacion. |

> El documento original menciona AES-256 para las contrasenas. La aplicacion usa el hash configurado por Laravel, que es la tecnica adecuada para contrasenas porque no es reversible.

## Ejecutar las pruebas automatizadas

Desde `backend`:

```powershell
php artisan test --filter AuthenticationTest
php artisan test --filter Authorization
php artisan test --filter DataIsolation
```

## Ejecutar OWASP ZAP Baseline

1. Levantar la aplicacion con `docker compose up -d --build` desde la raiz.
2. Confirmar que `http://localhost:3000` y `http://localhost:8080/api/ping` respondan.
3. Ejecutar desde la raiz:

```powershell
.\tests\security\run-zap-baseline.ps1
```

El script usa la imagen oficial estable de OWASP ZAP, hace un rastreo y analisis pasivo y genera HTML, JSON y Markdown en `tests/security/reports`. La opcion `-I` permite generar el informe aunque existan advertencias; los hallazgos deben clasificarse despues como confirmados o falsos positivos.

Para analizar directamente el backend puede indicarse otro destino:

```powershell
.\tests\security\run-zap-baseline.ps1 -Target "http://host.docker.internal:8080/api/ping"
```

El escaneo activo no debe ejecutarse contra produccion. Cuando se autorice en el ambiente local, se preparara un contexto autenticado para cubrir rutas privadas.

## Evidencia que debe conservarse

- Fecha, ambiente y commit evaluado.
- URL objetivo y parametros del escaneo.
- Reportes HTML y JSON generados.
- Capturas de los hallazgos relevantes.
- Tabla con severidad, evidencia, estado y accion correctiva.
- Resultado de la repeticion despues de cada correccion.
