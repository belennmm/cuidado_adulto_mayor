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

## Escaneo autenticado por roles

El archivo `openapi-security.yaml` describe 28 endpoints de lectura. El script inicia sesion como administrador, profesional y familiar, entrega cada token a ZAP mediante `ZAP_AUTH_HEADER_VALUE` y genera reportes separados sin guardar los tokens.

Modo seguro, sin ataques activos:

```powershell
.\tests\security\run-zap-authenticated.ps1
```

Modo activo, solamente contra el entorno local autorizado:

```powershell
.\tests\security\run-zap-authenticated.ps1 -Active
```

El script prepara tres cuentas exclusivas mediante `SecurityScanSeeder`; no modifica las contrasenas de los usuarios normales ni utiliza datos reales. Las credenciales pueden reemplazarse con parametros del script cuando se use otra base aislada.

## Evidencia que debe conservarse

- Fecha, ambiente y commit evaluado.
- URL objetivo y parametros del escaneo.
- Reportes HTML y JSON generados.
- Capturas de los hallazgos relevantes.
- Tabla con severidad, evidencia, estado y accion correctiva.
- Resultado de la repeticion despues de cada correccion.

## Prueba de volumen con k6

La prueba de volumen usa `grafana/k6:latest` y genera una carga progresiva de 10, 25 y 50 usuarios virtuales sobre `GET /api/me` y `GET /api/mobility-exercises`. El token se obtiene una sola vez en `setup`, evitando que el limitador de login distorsione la medicion. Los umbrales son menos de 1 % de errores, p95 menor a 1000 ms, p99 menor a 2000 ms y mas de 99 % de checks exitosos.

Ejecutar desde la raiz con el backend local levantado:

```powershell
.\tests\volume\run-k6.ps1
```

Los resumenes JSON se guardan en `tests/volume/reports` y no contienen contrasenas ni tokens.
