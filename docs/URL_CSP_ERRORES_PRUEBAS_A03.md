# SCRUM-832 a SCRUM-835: URL, CSP, errores e inyecciones

## Alcance

Este bloque implementa las cuatro subtareas siguientes de SCRUM-823 (A03). Las tareas SCRUM-836 a SCRUM-840 quedan para el siguiente bloque. No se realizaron acciones de Git, cambios de dependencias, despliegues ni migraciones sobre la base de datos de la aplicación.

## SCRUM-832: validar destinos URL

`frontend/js/url-utils.js` centraliza el análisis mediante `URL`: admite HTTP/HTTPS y rechaza credenciales embebidas, caracteres de control, espacios y barras invertidas. Para navegación de la aplicación exige el mismo origen. Se carga antes de los demás scripts en las 33 páginas.

- La navegación con carga valida antes de modificar la ubicación o activar efectos visuales. El interceptor cancela enlaces con protocolos peligrosos, incluso con mayúsculas o controles intercalados.
- Los enlaces de alertas se construyen con nodos DOM, contenido textual y destinos locales validados; los destinos rechazados usan `#`. Las notificaciones nativas aplican la misma validación al hacer clic.
- El cliente API valida su configuración y limita cada petición al origen y prefijo de ruta configurados, comprobando también la ruta normalizada. Rechaza fragmentos y escapes mediante `..`. No permite enviar tokens a URLs arbitrarias. `redirect: "error"` impide seguir redirecciones HTTP incluso si un consumidor intenta sobrescribirlo.
- Los videos de movilidad usan el origen fijo de YouTube y un identificador de exactamente 11 caracteres alfanuméricos, guion o guion bajo.
- Los textos como `javascript%3Aalert(1)` que el navegador trata como rutas relativas permanecen datos; nunca se decodifican manualmente para convertirlos en esquemas.

La configuración explícita de API HTTP/HTTPS puede apuntar a otro origen durante desarrollo. La CSP de producción exige mismo origen y utiliza el proxy `/api/` existente de Nginx; un despliegue separado requeriría declarar su origen exacto, sin habilitar todos los destinos HTTPS.

## SCRUM-833: reducir CSP

`frontend/nginx.conf` elimina `unsafe-inline` y el permiso genérico `connect-src https:`. Establece `default-src 'none'`, scripts y conexiones al mismo origen, atributos de script y estilo prohibidos, objetos y bases prohibidos, formularios locales y marcos limitados a `https://www.youtube.com/embed/`.

Las excepciones externas corresponden a recursos usados por las páginas: hojas de Google Fonts, fuentes de Google y CSS/fuentes de Boxicons 2.1.4 bajo sus rutas específicas de unpkg. Se mantienen imágenes locales y `data:`.

Los estilos de popup pasan a `frontend/css/app-popup.css`, cargados mediante un enlace externo. Los estilos de privacidad y del perfil profesional pasan a `page-privacy.css` y `page-user-settings.css`. No quedan bloques `<style>`, atributos `style`, scripts en línea ni handlers HTML en las páginas.

Las propiedades individuales de `element.style` utilizadas para gráficos y estados visuales se conservan. Su compatibilidad con `style-src-attr 'none'` está documentada en [MDN: style-src-attr](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/style-src-attr).

La API conserva su CSP independiente, con `default-src 'none'`.

## SCRUM-834: ocultar errores internos

`backend/bootstrap/app.php` normaliza las excepciones HTTP de API sin reproducir sus mensajes internos. Mantiene respuestas genéricas para 401/403/404/405 y oculta las restantes excepciones internas con `Error interno del servidor.`. Conserva los contratos de autenticación y validación que generan sus propias respuestas.

El callback final de excepciones cubre también `HttpResponseException` con estado 5xx. `SecurityHeaders` cubre respuestas 5xx devueltas directamente por controladores. Ambos sustituyen el cuerpo por JSON genérico, preservan el estado HTTP y eliminan `Content-Length` obsoleto. Las excepciones siguen el mecanismo habitual de reporte del servidor; no se suprime su registro.

En el frontend, los errores 5xx exponen únicamente el mensaje genérico, sin conservar trazas ni datos internos en `error.data`/`error.errors` o el texto del estado. Los cuerpos de error no JSON no se usan como mensajes para la interfaz.

## SCRUM-835: pruebas de ataques

`backend/tests/Feature/InjectionAndErrorDisclosureTest.php` agrega 14 casos:

- Errores de ejecución, SQL, HTTP, respuesta explícita y HttpResponseException con debug habilitado, conservando estados y sin SQL, rutas internas ni trazas.
- Tres payloads SQL en correo y otros tres en contraseña: ninguna autenticación, token ni alteración de cuentas.
- XSS almacenado: escritura y lectura HTTP de un nombre malicioso conservado como datos JSON, con `nosniff`.
- XSS reflejado en una ruta inexistente: respuesta genérica sin reproducir el payload.

`frontend/tests/unit/url-and-injection-security.test.js` agrega 32 casos: esquemas peligrosos, controles, URLs con credenciales, destinos externos, configuración de API, traversal, redirecciones, errores JSON/HTML 500, XSS reflejado en la lista de usuarios, alertas DOM, clics en notificaciones nativas, cancelación de enlaces, CSP, ausencia de estilos/scripts en línea y hoja externa de popup.

Complementa `ParameterizedQueriesTest` (valores SQL enlazados y listas permitidas) y las pruebas previas `safe-output-context`/`safe-admin-lists` de XSS almacenado y DOM en formularios, listados, notas, calendarios y atributos. Almacenar HTML como texto no implica interpretarlo: la defensa se verifica al representar los valores en cada contexto.

## Verificación

- Backend: 418 pruebas, 6933 aserciones.
- Frontend: 52 archivos y 168 pruebas.
- Pint: formato de los tres archivos PHP modificados.
- `node --check`: siete scripts modificados o nuevos.
- Referencias locales CSS/JS y prioridad de carga de URL guard verificadas en las 33 páginas.

Entorno instalado: PHP 8.2.12, Laravel 11.51.0, PHPUnit 11.5.55, Vitest 3.2.7 y jsdom 26.1.0. La ejecución no certifica las versiones superiores declaradas en los manifiestos. Las pruebas de backend usan SQLite en memoria.

La CSP se verifica mediante pruebas estáticas de configuración, markup y DOM. No se ejecutó Nginx ni una prueba de aplicación de CSP en navegador real: el motor de Docker no está disponible. Los cambios quedan listos para revisión y para que el usuario gestione su commit.
