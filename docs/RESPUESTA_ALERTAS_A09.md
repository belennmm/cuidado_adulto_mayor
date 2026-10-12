# SCRUM-970: validación y respuesta a alertas

## Alcance y responsables

Este procedimiento es manual: las alertas no bloquean cuentas automáticamente ni
envían correo o SMS. Antes de producción, designar un responsable de seguridad y
un suplente, definir un canal de escalamiento restringido y asignar quién revisa
el colector o los archivos diarios. Sin esa vigilancia no hay notificación activa.
El equipo técnico ejecuta contención autorizada; el responsable registra decisiones
y cierre. No realizar simulaciones contra producción ni con pacientes reales.

## Ensayo seguro

Desde `backend`, ejecutar con la configuración de pruebas:

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/SecurityAlertTest.php tests/Feature/SecurityAlertResponseTest.php --testdox
```

Las pruebas usan SQLite en memoria, caché `array` y, para los escenarios HTTP,
archivos temporales exclusivos bajo `storage/framework/testing`. No envían datos
al colector. Los umbrales se reducen solo dentro de las pruebas.

| Escenario | Resultado esperado |
| --- | --- |
| Logins HTTP incorrectos hasta el umbral | Respuestas 401 y una alerta `brute_force` firmada, correlacionada con la solicitud que alcanzó el umbral. |
| Accesos HTTP prohibidos de dos usuarios desde una IP | Respuestas 403; los contadores de usuarios distintos no se mezclan. |
| Lecturas HTTP sensibles del mismo usuario desde IP distintas | Respuestas 200; el contador sigue al usuario y produce `mass_access`. |
| Éxito público o login válido | No se considera un fallo de autenticación. |
| Solicitudes repetidas durante la pausa | Los eventos base siguen registrándose, sin repetir la alerta. |
| Vencimiento de ventana y pausa | El contador vence; una nueva secuencia puede generar otra alerta. |
| Caché no disponible | El evento base firmado permanece, se emite un aviso operativo seguro y se registra un evento firmado de nivel `error`. |
| Alteración de una alerta guardada | El verificador falla con código 1. |

Esto valida la detección local, no la entrega a un colector real ni la reacción
humana. Antes de producción realizar un ensayo autorizado en staging con usuarios
ficticios: comprobar llegada al colector, visibilidad para el responsable, revisión
de firmas, apertura del incidente y comunicación al suplente. Registrar fecha,
participantes, correlación de prueba, resultado y correcciones pendientes.

## Respuesta paso a paso

Priorizar `critical` (lecturas masivas) para revisar posible exposición y `error`
(detección degradada) para restaurar la vigilancia. Investigar también `warning`
(fuerza bruta y denegaciones). Los eventos `info` aportan contexto. Esta prioridad
no sustituye la evaluación humana ni constituye confirmación de un incidente.

1. **Recibir y registrar.** Abrir un incidente con identificador, regla, fecha UTC,
   actor si existe, correlación de la solicitud y responsable. No copiar datos
   clínicos, contraseñas, tokens ni cookies al ticket.
2. **Preservar evidencia.** Con acceso autorizado, copiar los archivos relevantes a
   un repositorio restringido antes de la retención, junto con su identificador de
   clave. Conservar originales y calcular un SHA-256 de la copia. La clave HMAC se
   custodia por separado, nunca se adjunta al ticket. No limpiar ni editar logs.
3. **Validar integridad.** Verificar la copia con la clave correspondiente:

   ```powershell
   php artisan security:audit-verify "RUTA_DE_LA_COPIA.log"
   ```

   Código 0 indica firmas válidas; código 1 indica archivo no disponible o registros
   inválidos. Ante fallos no confiar en el contenido: escalar, conservarlo y comparar
   con la copia del colector. Una firma válida no prueba que no falten líneas.
4. **Investigar.** Revisar eventos base por correlación, actor y hora. La IP guardada
   está enmascarada: no identifica por sí sola a una persona. Comparar con actividad
   esperada y cambios autorizados. Una alerta es una señal, no una acusación.
5. **Contener con autorización.** Para fuerza bruta, revisar bloqueos existentes y
   protección del login; para denegaciones, revisar permisos y posibles intentos de
   acceso; para lecturas masivas, verificar si hay un proceso legítimo y evaluar
   suspensión de cuenta o revocación de sesiones mediante mecanismos existentes.
   No bloquear una subred completa basándose solo en la IP enmascarada.
6. **Recuperar y cerrar.** Corregir la causa, comprobar servicio y auditoría,
   documentar incidente real o falso positivo, acciones, evidencias, aprobación y
   fecha de cierre. Ajustar umbrales con justificación, no para ocultar ataques.

## Detección degradada

El aviso `security_alert_detection_unavailable` requiere revisar disponibilidad y
permisos de caché, almacenamiento compartido y conectividad. No contiene la excepción
ni sus credenciales. Durante la falla, revisar los eventos base manualmente y, tras
restablecer el servicio, repetir el ensayo en staging. Si aparece
`security_audit_collector_unavailable`, revisar conectividad y TLS del colector y
la copia local: no asumir entrega remota ni borrar archivos pendientes.

## Registro mínimo del ensayo o incidente

- Identificador, entorno, responsable y suplente.
- Regla, fecha UTC, actor y correlación; sin contenidos sensibles.
- Ubicación restringida de evidencia, hash SHA-256 y resultado de firmas.
- Diagnóstico, contención autorizada y comunicaciones realizadas.
- Resultado de recuperación, pendientes y aprobación del cierre.
