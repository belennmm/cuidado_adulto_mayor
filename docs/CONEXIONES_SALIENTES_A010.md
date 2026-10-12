# A010: conexiones salientes y deteccion de incidentes

## Alcance e inventario (SCRUM-977)

La aplicacion no ofrece un endpoint que descargue una URL proporcionada por el usuario.
El inventario de conexiones del backend es:

| Conexion | Origen | Destino | Control |
| --- | --- | --- | --- |
| Contraseñas comprometidas | `CompromisedPasswords` | `https://api.pwnedpasswords.com:443/range/{prefijo}` | `SecureExternalHttpClient` y `OutboundDestinationPolicy` |
| Colector opcional de auditoria | `AuditCollectorHandler` | Un host y puerto TLS aprobados por el operador | Misma politica de destinos; socket conectado a la IP validada |
| PostgreSQL, cache, SMTP | Configuracion de Laravel | Servicios definidos por el operador | No reciben URL de usuarios; credenciales y redes se administran en despliegue |
| Slack/Papertrail de Monolog | Canales opcionales de `logging.php` | Configuracion del operador | Inactivos con `LOG_CHANNEL=stack`, `LOG_STACK=single`; no habilitarlos como alternativa al cliente seguro o al colector aprobado |
| Frontend | `fetch` hacia la API de la aplicacion | URL configurada de la API | Se ejecuta en el navegador, no constituye una descarga del servidor |

No se agregaron dependencias Composer ni migraciones. Las migraciones de autenticacion
que ya existen en `main` pertenecen a A07 y no se eliminan ni vuelven a crear en esta tarea.

## Politica de conexiones (SCRUM-978 a SCRUM-988)

- La interfaz HTTP acepta un prefijo SHA-1 de cinco caracteres, no una URL libre.
  El cuerpo y la contraseña nunca se envian al servicio externo: solamente el prefijo,
  con `Add-Padding: true`, como en la implementacion anterior.
- Solo HTTPS y el dominio exacto `api.pwnedpasswords.com`, puerto 443, estan permitidos.
  Una nueva integracion requiere codigo revisado y una entrada explicita en
  `backend/config/outbound.php`; no hay comodines ni sufijos de dominio.
- Se rechazan credenciales en URL, fragmentos, query strings, controles, espacios,
  barras invertidas, autoridades codificadas, rutas ambiguas, esquemas alternativos,
  direcciones literales y representaciones decimales/octal/hexadecimal de IPv4.
  El host y esquema se normalizan a minusculas antes de compararse.
- El resolvedor consulta A, AAAA y CNAME. Revisa todos los resultados y limita ciclos,
  profundidad y cantidad de direcciones. Un solo resultado no publico bloquea todo
  el destino. No se selecciona una IP publica ignorando otras internas.
- Se bloquean loopback, privadas, link-local, multicast, documentacion, reservadas,
  CGNAT, metadatos cloud y rangos de transicion IPv6. Solo se acepta IPv6 global
  dentro de `2000::/3`, con exclusiones adicionales. Tambien se bloquea el endpoint
  especial de plataforma Azure `168.63.129.16`.
- cURL recibe la IP validada mediante `CURLOPT_RESOLVE`. Conserva el dominio original
  para Host, SNI y validacion del certificado. Se fuerzan conexiones nuevas, se evita
  reutilizarlas y se deshabilitan proxies heredados del entorno. Cada solicitud
  resuelve y valida de nuevo: no hay una segunda resolucion DNS para conectar.
- Las redirecciones estan deshabilitadas: limite **cero**, incluidos 301, 302, 303,
  307 y 308. Una respuesta de redireccion provoca fallo, no una segunda descarga.
- Conexion HTTP: 2 segundos. Transferencia HTTP: 4 segundos. Respuesta: 1.000.000 bytes.
  El flujo de escritura se corta antes de superar el presupuesto, incluso sin
  Content-Length; tambien se valida la cabecera. No se descomprime contenido remoto.
  Los tiempos HTTP no incluyen el resolvedor DNS nativo: en produccion configurar
  tiempos e intentos del resolvedor del sistema y monitorizar su disponibilidad.
- Certificados y nombre TLS siempre se verifican. Si falta cURL, DNS falla o un
  destino es rechazado, la comprobacion de contraseñas responde 503 y no guarda
  nuevas credenciales. Los errores no exponen URL, contraseña, prefijo ni detalles
  de la infraestructura.

Referencias de transporte: [cURL CURLOPT_RESOLVE](https://curl.se/libcurl/c/CURLOPT_RESOLVE.html)
y [opciones de Guzzle](https://docs.guzzlephp.org/en/stable/request-options.html).

### Colector TLS opcional

La politica HTTP no se aplica como excepcion al colector. Este tiene su propia
lista exacta de hosts/puertos aprobados, valida todos los resultados DNS y conecta
al IP comprobado con el nombre original en `peer_name`. Una denegacion o caida
remota conserva el registro firmado local y genera un aviso operativo seguro.

Ejemplo de configuracion del operador (sustituir por un dominio publico real):

```dotenv
SECURITY_AUDIT_COLLECTOR_URL=tls://audit.example.com:6514
OUTBOUND_AUDIT_COLLECTOR_HOST=audit.example.com
OUTBOUND_AUDIT_COLLECTOR_PORT=6514
```

Con los valores predeterminados el colector sigue apagado. No se permite apuntarlo
a un servicio privado de Docker para saltarse la proteccion. Si el despliegue
requiere un colector interno, debe diseñarse una excepcion separada, revisada y
restringida por infraestructura; no ampliar a todas las redes privadas.

## Señales y trazabilidad (SCRUM-989 a SCRUM-997)

| Subtarea | Comportamiento |
| --- | --- |
| 989 | Los 404 generan `resource.not_found`; los recursos de otros usuarios siguen generando 403/404 sin revelar datos y se auditan. Se guarda la plantilla de ruta, no el identificador/URL arbitrario del atacante. |
| 990 | Se cuentan denegaciones y recursos inexistentes. Los intentos fallidos de login se correlacionan tanto por IP como por cuenta mediante HMAC, incluso al cambiar de IP. Las alertas mantienen ventana y cooldown de A09. |
| 991 | Cambios reales de rol/aprobacion producen `security.alert.account_change`, una vez por solicitud, ademas del evento base. Un cambio sin diferencias no afirma cambios de privilegios. |
| 992 | Creacion, modificacion y eliminacion de administradores generan `administrative_account.create`, `.update` y `.delete`, ademas del evento de usuario. Se conserva la condicion de administrador anterior si se cambia el rol. |
| 993 | Una lectura sensible o exportacion administrativa grande genera `sensitive_data.large_response` y alerta critica; umbrales predeterminados: 1 MiB o 100 filas efectivamente retornadas. No se cuenta el total paginado ni se copia el contenido al log. |
| 994 | `SecurityEvent` conserva fecha/hora UTC con microsegundos y sufijo `Z`. |
| 995 | Se reutilizan allowlist de campos, enmascaramiento, eliminacion de controles y redaccion de secretos de A09/A07. Las excepciones nunca se registran con su SQL, bindings o texto original. |
| 996 | Se reutiliza `X-Correlation-ID` y el UUID por solicitud; todos los eventos asociados lo conservan. Los eventos fuera de una solicitud reciben un UUID propio. |
| 997 | Fallos de base de datos y almacenamiento se clasifican en el manejador de excepciones; fallos HTTP de autenticacion y errores generales se registran por respuesta. Los fallos salientes generan `infrastructure.outbound_request`. Si no puede escribirse auditoria, el aviso seguro va al log operativo sin recursion. |

Umbrales configurables:

```dotenv
SECURITY_AUDIT_LARGE_RESPONSE_BYTES=1048576
SECURITY_AUDIT_LARGE_RESPONSE_ROWS=100
```

Se verifica el tamaño de respuestas JSON y archivos descargables con tamaño conocido.
No hay descargas de streaming en los controladores actuales. Si se agrega una,
debe instrumentarse su contador de bytes durante el envio, sin acumularla en memoria.
Las alertas indican actividad que debe investigar una persona; no prueban por si
solas que ocurrio un ataque ni bloquean cambios administrativos legitimos.

## Verificacion periodica (SCRUM-998)

```powershell
php backend/artisan security:audit-health
```

El comando escribe un evento nuevo, vuelve a leer desde la posicion anterior del
archivo y verifica su UUID y firma. No considera suficiente que el archivo exista
o contenga eventos viejos. Devuelve codigo 1 si no se puede escribir/leer, se agota
la cuota o la firma no verifica; emite `security_audit_health_failed` sin contenido
sensible. La lectura de comprobacion esta limitada a 1 MiB.

Se programa cada cinco minutos sin solaparse. En produccion es necesario tener
activo el scheduler habitual de Laravel y monitorizar codigo de salida/avisos.
No expone una ruta publica de diagnostico ni verifica la recepcion remota del colector.

## Docker, CI y desarrollo

- El Dockerfile instala explicitamente cURL y su dependencia del sistema.
- Los workflows instalan la extension cURL para las pruebas.
- Se mantienen las claves temporales de construccion/CI agregadas en A09.
  No se introduce ninguna clave fija ni se desactiva la validacion de produccion.
- No se cambian `read_only`, volúmenes, permisos, roles o el override de desarrollo.
  Validar la configuracion combinada con `docker compose ... config --quiet` antes
  de levantar servicios. Una validacion de Compose no equivale a construir la imagen.
- En despliegue aplicar ademas reglas de salida en firewall/egress para el proceso
  de la aplicacion, con DNS y servicios de datos explicitamente autorizados. El filtro
  PHP protege las conexiones inventariadas, pero no reemplaza aislamiento de red
  frente a codigo arbitrario que pueda ejecutar un atacante.
- No usar los canales remotos opcionales de Monolog para eludir estas restricciones.
  Una nueva salida de red debe agregarse al inventario, pasar por la politica segura
  y contar con pruebas. Las conexiones de base de datos/correo no admiten URLs de usuario.

## Pruebas reproducibles

Desde la raiz, para evitar el problema de comillas y `|` del launcher de Windows:

```powershell
php backend/vendor/phpunit/phpunit/phpunit -c backend/phpunit.xml --filter "Outbound|SecureExternalHttpClient|SecurityAuditHealthCheck|PasswordPolicy|SecurityAlert" --testdox
php backend/vendor/phpunit/phpunit/phpunit -c backend/phpunit.xml
```

Las pruebas usan DNS y HTTP simulados: no consultan contraseñas reales, servicios
externos ni metadatos cloud. Cubren IPv4/IPv6, formatos alternativos, CNAME interno,
ciclos DNS, rebinding, rechazo antes de conectar, pinning, verificacion TLS, proxies,
redirecciones, limite real de escritura, fallos de servicios, alertas y heartbeat
firmado. Se mantiene la suite anterior de A09 y autenticacion.
