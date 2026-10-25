<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
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
        Route::post('/api/test-only/validation', function () {
            throw ValidationException::withMessages(['email' => ['El correo no es válido.']]);
        });

        $this->postJson('/api/test-only/validation')
            ->assertUnprocessable()
            ->assertJsonStructure(['code', 'message', 'errors' => ['email']])
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.email.0', 'El correo no es válido.');
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
