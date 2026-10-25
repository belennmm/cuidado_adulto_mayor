# Contrato uniforme de errores de la API

Las respuestas de error bajo `/api/*` usan la misma estructura:

```json
{
  "code": "validation_failed",
  "message": "Los datos enviados no son válidos.",
  "errors": {
    "email": ["El correo no es válido."]
  }
}
```

- `code`: identificador estable para que el frontend pueda decidir qué hacer.
- `message`: texto breve que puede mostrarse al usuario.
- `errors`: errores asociados a campos; es `{}` cuando no aplica.
- El estado HTTP conserva el significado de la respuesta y sigue siendo la fuente de verdad para el transporte.

La normalización se centraliza en `ApiErrorResponse` y cubre excepciones, respuestas directas de middleware y respuestas generadas por validación. Los errores 5xx siempre devuelven un mensaje genérico y no exponen detalles internos. El backend conserva el status y los encabezados de seguridad, agrega `X-Correlation-ID` y recalcula el cuerpo JSON sin un `Content-Length` obsoleto.

El cliente `CuidadoApi` conserva `status`, `code`, `data` y `errors` en el objeto `Error`. Para `validation_failed`, muestra primero el primer mensaje de campo; los demás errores usan `message`.

| HTTP | Código por defecto | Uso |
|---:|---|---|
| 400 | `bad_request` | Solicitud mal formada |
| 401 | `unauthenticated` | Sesión o autenticación requerida |
| 403 | `forbidden` | Acción no autorizada |
| 404 | `not_found` | Recurso no disponible |
| 405 | `method_not_allowed` | Método HTTP no permitido |
| 409 | `conflict` | Conflicto con el estado actual |
| 413 | `payload_too_large` | Solicitud excede el tamaño permitido |
| 419 | `csrf_token_mismatch` | Protección CSRF vencida |
| 422 | `validation_failed` | Validación de campos o regla de negocio |
| 428 | `reauthentication_required` | Confirmación de identidad necesaria |
| 429 | `too_many_requests` | Límite de solicitudes alcanzado |
| 5xx | `internal_server_error` / `service_unavailable` | Fallo genérico del servidor |

Prueba del contrato y regresiones desde `backend`:

```powershell
php vendor/phpunit/phpunit/phpunit --filter "ApiErrorContractTest|ApiServerErrorResponseTest|ApiErrorLogPrivacyTest"
```
