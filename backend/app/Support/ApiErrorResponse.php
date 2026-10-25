<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

final class ApiErrorResponse
{
    public static function normalize(Response $response): Response
    {
        if ($response->getStatusCode() < 400) {
            return $response;
        }

        $status = $response->getStatusCode();
        $payload = json_decode((string) $response->getContent(), true);
        $payload = is_array($payload) ? $payload : [];
        $serverError = $status >= 500;
        $message = $serverError
            ? 'Error interno del servidor.'
            : (is_string($payload['message'] ?? null) && $payload['message'] !== ''
                ? $payload['message']
                : self::defaultMessage($status));
        $code = $serverError
            ? ($status === 503 ? 'service_unavailable' : 'internal_server_error')
            : (is_string($payload['code'] ?? null) && preg_match('/^[a-z][a-z0-9_]{1,63}$/', $payload['code'])
                ? $payload['code']
                : self::defaultCode($status));
        $errors = is_array($payload['errors'] ?? null) ? $payload['errors'] : (object) [];

        $normalizedPayload = [
            'code' => $code,
            'message' => $message,
            'errors' => $errors,
        ];
        if ($status === 429 && is_numeric($payload['retry_after'] ?? null)) {
            $normalizedPayload['retry_after'] = max(0, (int) $payload['retry_after']);
        }

        $response->setContent(json_encode(
            $normalizedPayload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->remove('Content-Length');

        return $response;
    }

    private static function defaultCode(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            413 => 'payload_too_large',
            419 => 'csrf_token_mismatch',
            422 => 'validation_failed',
            428 => 'reauthentication_required',
            429 => 'too_many_requests',
            default => 'http_error',
        };
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'La solicitud no es válida.',
            401 => 'No autenticado.',
            403 => 'No tienes permiso para realizar esta accion.',
            404 => 'Recurso no encontrado.',
            405 => 'Metodo HTTP no permitido.',
            413 => 'La solicitud excede el tamano permitido.',
            419 => 'La proteccion CSRF expiro. Recarga la pagina e intenta nuevamente.',
            422 => 'Los datos enviados no son válidos.',
            428 => 'Se requiere confirmar la contraseña actual.',
            429 => 'Demasiadas solicitudes. Intenta nuevamente mas tarde.',
            default => 'No se pudo completar la solicitud.',
        };
    }
}
