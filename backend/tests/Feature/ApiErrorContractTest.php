<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiErrorContractTest extends TestCase
{
    public function test_401_response_uses_the_common_error_contract(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonStructure(['code', 'message', 'errors'])
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonPath('errors', []);
    }

    public function test_403_response_uses_the_common_error_contract(): void
    {
        Route::get('/api/test-only/forbidden', fn () => response()->json(['message' => 'Acceso denegado.'], 403));

        $this->getJson('/api/test-only/forbidden')
            ->assertForbidden()
            ->assertJsonStructure(['code', 'message', 'errors'])
            ->assertJsonPath('code', 'forbidden')
            ->assertJsonPath('message', 'Acceso denegado.')
            ->assertJsonPath('errors', []);
    }

    public function test_422_response_preserves_field_errors_in_the_common_contract(): void
    {
        Route::post('/api/test-only/validation', fn () => response()->json([
            'message' => 'El horario es inválido.',
            'errors' => ['end_time' => ['end_time debe ser mayor que start_time.']],
        ], 422));

        $this->postJson('/api/test-only/validation')
            ->assertUnprocessable()
            ->assertJsonStructure(['code', 'message', 'errors' => ['end_time']])
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('message', 'El horario es inválido.')
            ->assertJsonPath('errors.end_time.0', 'end_time debe ser mayor que start_time.');
    }

    public function test_429_response_preserves_a_safe_retry_after_value(): void
    {
        Route::get('/api/test-only/rate-limit', fn () => response()->json([
            'message' => 'Demasiados intentos.',
            'retry_after' => 60,
        ], 429));

        $this->getJson('/api/test-only/rate-limit')
            ->assertTooManyRequests()
            ->assertJsonStructure(['code', 'message', 'errors', 'retry_after'])
            ->assertJsonPath('code', 'too_many_requests')
            ->assertJsonPath('retry_after', 60);
    }

    public function test_normalizing_an_error_preserves_response_cookies(): void
    {
        Route::get('/api/test-only/error-cookie', fn () => response()->json([
            'message' => 'Solicitud inválida.',
            'errors' => [],
        ], 422)->withCookie(cookie('error-cookie', 'preserve-me')));

        $this->getJson('/api/test-only/error-cookie')
            ->assertUnprocessable()
            ->assertCookie('error-cookie', 'preserve-me', false);
    }

    public function test_500_response_uses_a_safe_common_error_contract(): void
    {
        Route::get('/api/test-only/server-error', fn () => throw new RuntimeException('secret internal detail'));

        $this->getJson('/api/test-only/server-error')
            ->assertInternalServerError()
            ->assertExactJson([
                'code' => 'internal_server_error',
                'message' => 'Error interno del servidor.',
                'errors' => [],
            ])
            ->assertDontSee('secret internal detail');
    }
}
