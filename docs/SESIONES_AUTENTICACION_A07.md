# SCRUM-907 / A07: primer bloque, SCRUM-908 a SCRUM-912

Fecha: 2026-10-11. Entrega local preparada para que el responsable cree su commit. No se ejecutaron comandos Git ni migraciones contra la base de datos de trabajo.

## Alcance del bloque

| Subtarea | Implementación |
| --- | --- |
| SCRUM-908 | El navegador autentica con una sesión del servidor. El login devuelve usuario y mensaje, sin token ni creación de PersonalAccessToken. Se eliminan credenciales antiguas de localStorage y sessionStorage; solo quedan metadatos de interfaz en sessionStorage. |
| SCRUM-909 | Cookie de sesión cifrada, HttpOnly, Secure y SameSite=Lax por defecto, limitada al host y ruta `/`. Producción rechaza Secure/HttpOnly desactivados, SameSite inválido y almacenamiento de sesión en cookie/array/null. |
| SCRUM-910 | Inicialización pública `GET /api/csrf-cookie`, seguida de `X-XSRF-TOKEN` en escrituras. Login, registro, modificaciones y logout requieren CSRF. Orígenes de escritura limitados a los configurados o al propio servidor; CORS permite credenciales solo a orígenes explícitos. |
| SCRUM-911 | Comprobación en el servidor de 15 minutos de inactividad y límite absoluto de 60 minutos, configurables. Al vencer se invalida la sesión y se devuelve 401. |
| SCRUM-912 | Login regenera el identificador de sesión destruyendo el anterior y renueva CSRF. Una cookie anterior no permite consultar `/api/me`. |

Este documento describe las primeras cinco subtareas. El [segundo bloque, SCRUM-913 a SCRUM-917](SESIONES_AUTENTICACION_A07_BLOQUE2.md), completa otras cinco; quedan 16 para bloques posteriores: 5 + 5 + 5 + 1.

## Flujo de navegador

`api-config.js` siempre incluye cookies (`credentials: include`), identifica el flujo con `X-Cuidado-Session: 1` y descarta encabezados Authorization proporcionados por sus consumidores. Antes de login/registro renueva CSRF incluso si persiste una cookie antigua; las demás escrituras inicializan CSRF cuando falta. La cookie `XSRF-TOKEN` es legible por JavaScript y contiene la protección CSRF, no la credencial de autenticación. La cookie de sesión es HttpOnly.

`BrowserSessions` también reconoce cookies de sesión y solicitudes con Origin/Referer. Esas solicitudes nunca recurren a Bearer como alternativa ni eximen CSRF por incluir Authorization. Los clientes API existentes sin contexto de navegador conservan el contrato Bearer y sus capacidades. Las Policies, límites por rol, denegación por defecto, aprobación y huella de seguridad continúan vigentes en ambos flujos. La guardia web se habilita para Sanctum únicamente dentro del flujo de navegador.

Las guardias de página consultan `/api/me` antes de mostrar contenido protegido. Los metadatos locales sirven para la interfaz; el servidor decide el acceso en cada petición. Los 401 limpian esos metadatos. El logout envía POST con CSRF, invalida la sesión en el servidor y solo entonces limpia la interfaz y navega al login. Si falla, permite reintentar y muestra el error. Un 419 no repite automáticamente una escritura.

Las cookies comparten identidad entre pestañas del mismo navegador: iniciar sesión con otra cuenta cambia la identidad del servidor en todas ellas. Las páginas vuelven a consultar la identidad al cargarse; las operaciones posteriores siempre están sujetas a permisos actuales del servidor. Ya no se conservan tokens independientes por rol/pestaña.

## Configuración y puesta en marcha

```dotenv
SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_IDLE_TIMEOUT=15
SESSION_ABSOLUTE_TIMEOUT=60
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PATH=/
SESSION_DOMAIN=null
```

`docker-compose.yml` expone los tres tiempos configurables. La tabla `sessions` ya forma parte de las migraciones existentes. `.env.example` contiene los valores anteriores; no se modifica el `.env` de trabajo. Las pruebas usan bases SQLite efímeras.

Producción necesita HTTPS y sesiones persistidas en el servidor. El driver `cookie` se rechaza también en el flujo del navegador porque no permite revocar una copia capturada mediante eliminación de estado del servidor. El entorno HTTP local puede usar la excepción `SESSION_SECURE_COOKIE=false` ya presente en `docker-compose.dev.yml`, con APP_ENV local. El frontend servido por nginx utiliza el proxy `/api`, en el mismo origen; también funcionan puertos distintos del mismo hostname con CORS explícito. Un frontend en otro hostname no puede leer la cookie CSRF limitada al host: debe usar el proxy del mismo origen o configurar deliberadamente un dominio compartido confiable y sus orígenes.

La inactividad mide ausencia de solicitudes autenticadas, incluyendo las consultas periódicas del frontend. Esas consultas renuevan el límite de inactividad, pero nunca el límite absoluto. La cookie puede seguir almacenada después de vencer la autorización; su presencia no concede acceso. Cambios de contraseña, aprobación o rol invalidan la huella de la sesión. También se evita que una solicitud de cambio de contraseña vuelva a guardar una sesión autenticada después de revocarla.

Las credenciales antiguas del navegador se eliminan y requieren nuevo login; no se convierten en cookies ni se revocan masivamente credenciales API de terceros. Los tokens API conservan su expiración y revocación existentes.

## Verificación

- Pruebas HTTP con cookies cifradas reales y driver database: banderas de cookie, login sin token, rotación de sesión/CSRF, rechazo de la cookie anterior, CSRF ausente/incorrecto, origen malicioso, ausencia de bypass por Sec-Fetch-Site o Bearer, escrituras protegidas, logout y replay, inactividad, caducidad absoluta, permisos de los cinco roles/alias admitidos y revocación por aprobación/contraseña.
- Pruebas de arranque que rechazan configuraciones de sesión inseguras en producción.
- Pruebas frontend de eliminación de credenciales legadas, metadatos sin token, identidad validada en servidor, guardias de página, cookies/CSRF, concurrencia de inicialización, cookies bloqueadas, errores 401/419 y confirmación de logout en el servidor.
- Las pruebas de CSRF no utilizan la exención habitual de Laravel durante pruebas. La validación de token permanece obligatoria también con versiones que aceptan Sec-Fetch-Site.

Los resultados finales y el entorno efectivo se registran al cerrar la verificación. El entorno local instalado usa PHP 8.2.12, Laravel 11.51 y PHPUnit 11.5.55; el manifiesto solicita PHP 8.3 / Laravel 13 / PHPUnit 12. El frontend se verificó con Vitest 3.2.7 y jsdom 26.1 instalados, frente a Vitest 5 / jsdom 30 declarados. No se sustituyeron dependencias. Debe validarse ese entorno declarado en CI antes de publicar.

Referencias del framework: [autenticación SPA con Sanctum](https://laravel.com/framework/docs/11.x/sanctum) y [middleware CSRF de Laravel 13](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Foundation/Http/Middleware/PreventRequestForgery.php). Se reutilizan sesiones, cifrado de cookies y validación de tokens del framework.


Cierre posterior (2026-10-11): las 26 subtareas SCRUM-908 a SCRUM-933 quedan implementadas entre los cinco bloques. Resultado final: 630 pruebas backend / 8650 aserciones y 200 frontend, sin fallos. [MFA, proteccion de secretos, migracion pendiente y validacion final](SESIONES_AUTENTICACION_A07_BLOQUE5.md). El correo de recuperacion del bloque 3 fue actualizado para entregar el codigo separado de la URL.
