# Prueba no funcional de seguridad con OWASP ZAP

## Objetivo

Evaluar los requisitos no funcionales de seguridad definidos para Organízate y obtener una linea base reproducible de vulnerabilidades visibles desde el frontend.

## Requisitos evaluados

| ID | Requisito | Resultado actual | Evidencia |
|---|---|---|---|
| RNF-16 | Las contrasenas deben almacenarse de forma segura. | Cumple. Laravel genera un hash no reversible y la prueba confirma que el valor almacenado no coincide con la contrasena. | `AuthenticationTest::test_registration_creates_a_pending_user_and_normalizes_role` |
| RNF-17 | Bloquear el acceso despues de 5 intentos fallidos. | Cumple. El sexto intento se rechaza con HTTP 429 durante 5 minutos. Un acceso correcto limpia el contador anterior. | Pruebas de limite de intentos en `AuthenticationTest`. |
| RNF-18 | Solo usuarios autorizados pueden acceder a datos medicos. | Cumple en los casos automatizados existentes de roles y aislamiento de datos. | 8 pruebas y 37 aserciones de autorizacion y aislamiento aprobadas. |
| RNF-23 | Mostrar aviso de privacidad antes del registro. | Cumple. El formulario muestra un aviso y enlaza la politica completa antes del boton de registro. | `frontend/pages/register.html` y `frontend/pages/privacy.html`. |
| RNF-24 | Guardar el consentimiento del 100 % de los usuarios. | Cumple para todo nuevo autorregistro. La API rechaza el registro sin aceptacion y almacena fecha y version de la politica. | Migracion de consentimiento y pruebas de `AuthenticationTest`. |
| RNF-42 | Comunicacion entre modulos mediante APIs seguras. | Cumple parcialmente. Las rutas privadas usan Sanctum y el frontend ahora incluye encabezados defensivos; el ambiente local aun usa HTTP. | Pruebas de API y reporte ZAP. |

## Herramienta y ambiente

- Herramienta: OWASP ZAP Baseline desde la imagen oficial `ghcr.io/zaproxy/zaproxy:stable`.
- Fecha: 28 de septiembre de 2026.
- Objetivo: `http://host.docker.internal:3000`.
- Ambiente: contenedores locales de frontend Nginx, backend Laravel y PostgreSQL.
- Modalidad: rastreo de 2 minutos y analisis pasivo. No se ejecutaron ataques activos.
- Cobertura: 17 URL encontradas.

El procedimiento puede repetirse con `tests/security/run-zap-baseline.ps1`. Los reportes HTML, JSON y Markdown se generan en `tests/security/reports` y no se versionan porque son evidencia producida por cada ejecucion.

## Resultado inicial

El primer escaneo obtuvo 0 fallos y 9 tipos de advertencias en la salida resumida. Los hallazgos corregibles mas importantes fueron:

- Falta de Content Security Policy.
- Falta de proteccion contra clickjacking.
- Falta de `X-Content-Type-Options`.
- Divulgacion de version del servidor.
- Falta de Permissions Policy.
- Falta de politicas Cross-Origin.

## Correcciones realizadas

Se configuro Nginx para:

- Ocultar la version del servidor.
- Definir Content Security Policy.
- Evitar carga en marcos no autorizados.
- Evitar interpretacion incorrecta de tipos MIME.
- Limitar camara, microfono y geolocalizacion.
- Aplicar politicas de apertura y recursos entre origenes.
- Establecer una politica segura de referencia.

En el backend se implemento un limite de cinco intentos fallidos por combinacion de correo e IP, con bloqueo de cinco minutos.

## Resultado posterior a las correcciones

La segunda ejecucion obtuvo:

| Indicador | Resultado |
|---|---:|
| URL analizadas | 17 |
| Reglas aprobadas | 62 |
| Fallos | 0 |
| Tipos de advertencia | 5 |

Las advertencias iniciales sobre CSP ausente, clickjacking, tipo MIME, version del servidor, Permissions Policy y las politicas Cross-Origin configuradas dejaron de aparecer.

## Advertencias pendientes

| Hallazgo | Evaluacion | Accion propuesta |
|---|---|---|
| `style-src 'unsafe-inline'` | Riesgo aceptado temporalmente porque existen estilos en linea y estilos asignados desde JavaScript. | Mover estilos en linea a archivos CSS y sustituir atributos dinamicos por clases; despues eliminar `unsafe-inline`. |
| Subresource Integrity ausente | El frontend carga Google Fonts y Boxicons desde servicios externos. | Alojar esos recursos dentro del proyecto o fijar archivos con hash SRI verificable. |
| Cross-Origin-Embedder-Policy ausente | Activarla ahora podria bloquear fuentes, iconos y videos externos. | Alojar dependencias localmente y validar compatibilidad antes de usar `require-corp`. |
| Contenido no almacenable | Resultado de la politica `no-store`; no representa una vulnerabilidad directa. | Revisar la politica de cache y permitir cache solo para recursos estaticos versionados. |
| Aplicacion web moderna | Hallazgo informativo. | No requiere correccion. |

## Limitaciones y siguiente paso

El escaneo Baseline es pasivo y no demuestra por si solo que todas las rutas autenticadas sean seguras. Falta preparar un contexto ZAP autenticado para los roles administrador, profesional y familiar, y ejecutar un escaneo activo exclusivamente en el ambiente local. Las cuentas creadas antes de esta funcionalidad y las creadas administrativamente conservan el consentimiento como nulo; antes de produccion debe definirse un flujo de renovacion o una base legal distinta para esas cuentas, sin registrar aceptaciones ficticias.
