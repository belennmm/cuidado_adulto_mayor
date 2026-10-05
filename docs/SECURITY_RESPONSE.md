# Respuesta y endurecimiento de seguridad

## Incidente confirmado

Se confirmó que `cuidado_backup.sql` contenía información personal, hashes de contraseñas, sesiones y tokens de acceso. El archivo se retiró del árbol actual, pero continúa en commits anteriores hasta que una persona autorizada coordine la reescritura del historial remoto.

Tratar todos los datos del respaldo como comprometidos. No copiar valores del respaldo a incidencias, chats o registros.

## Acciones operativas obligatorias

1. Revocar todos los tokens de Sanctum: `php artisan tinker --execute="Laravel\\Sanctum\\PersonalAccessToken::query()->delete();"` o eliminar las filas de `personal_access_tokens` mediante un procedimiento aprobado.
2. Invalidar sesiones activas y tokens de restablecimiento.
3. Forzar cambio de contraseña de las cuentas presentes en el respaldo y avisar a sus propietarios por un canal verificado.
4. Generar una `APP_KEY` distinta por ambiente. Nunca reutilizar una clave comprometida; planificar el impacto sobre datos cifrados antes de rotarla.
5. Rotar contraseñas de base de datos, pgAdmin y cualquier secreto que haya coincidido con el respaldo.
6. Abrir un incidente formal con responsable, cronología, alcance, personas afectadas y evidencia preservada en almacenamiento restringido.
7. Coordinar una ventana para limpiar todas las ramas, etiquetas y forks, hacer `force-push` y pedir un clon nuevo a cada colaborador.

Estas acciones no se ejecutan automáticamente desde el repositorio.

## Respaldos

- Generarlos con `tools/backup-encrypted.ps1` hacia almacenamiento externo administrado; requiere `pg_dump` y `age`.
- Usar una clave de cifrado custodiada fuera del servidor y del repositorio.
- Aplicar mínimo privilegio: la identidad que escribe respaldos no debe leerlos ni borrarlos; la identidad de restauración se concede temporalmente.
- Definir retención automática y eliminación verificable según la política de datos.
- Probar restauraciones periódicamente en un ambiente aislado y con datos anonimizados.

## Datos de desarrollo y copias de producción

Desarrollo, pruebas y demostraciones deben usar seeders completamente ficticios. Para una copia excepcional de producción:

1. Obtener autorización documentada y definir campos sensibles.
2. Excluir tokens, sesiones, contraseñas, bitácoras y adjuntos.
3. Sustituir nombres, correos, teléfonos, ubicaciones, fechas y texto libre con valores sintéticos no reversibles.
4. Generalizar fechas y ubicaciones cuando la combinación pueda reidentificar a una persona.
5. Validar con consultas automáticas que no permanezcan dominios, teléfonos o identificadores reales.
6. Cifrar durante traslado y reposo, limitar su vigencia y registrar su eliminación.

## Despliegue HTTPS

Terminar TLS en un proxy o balanceador con certificado emitido para el dominio. Redirigir HTTP a HTTPS y reenviar `X-Forwarded-Proto`. En producción mantener `APP_URL=https://...`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true` y `DB_SSLMODE=require`. Verificar HSTS solo después de confirmar que todos los subdominios funcionan por HTTPS.

## Rotación periódica

- Secretos de servicio: cada 90 días o inmediatamente ante exposición.
- Credenciales humanas privilegiadas: aplicar MFA y rotar ante cambio de función o incidente.
- Tokens: expiración de 60 minutos; revocar al cerrar sesión, desactivar cuenta o cambiar contraseña.
- La aplicación asigna `expires_at` a cada token y elimina tokens, sesiones y enlaces de restablecimiento cuando cambia la contraseña o se elimina/desactiva una cuenta.
- Cada rotación debe registrar propietario, fecha, sistemas afectados, verificación y plan de reversión, nunca el valor secreto.

## Verificación

- Ejecutar `./tools/scan-secrets.ps1` antes de cada commit.
- Activar el hook versionado una vez por clon con `git config core.hooksPath .githooks`.
- La acción `Secret scan` se ejecuta en cada push y pull request.
- Revisar que logs y respuestas no incluyan contraseñas, tokens, datos médicos ni cuerpos completos de solicitudes.
- Ejecutar `php artisan test --filter=SecurityControlsTest` para comprobar hashing, expiración, revocación, cabeceras y respuestas sin secretos.
