<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequestCorrelationIdTest extends TestCase
{
    public function test_the_response_and_request_share_a_server_generated_uuid(): void
    {
        Route::get('/correlation-test', fn (Request $request) => response()->json([
            'correlation_id' => $request->attributes->get('correlation_id'),
        ]));

        $response = $this->getJson('/correlation-test')->assertOk();
        $correlationId = $response->headers->get('X-Correlation-ID');

        $this->assertTrue(Str::isUuid($correlationId));
        $response->assertJsonPath('correlation_id', $correlationId);
    }

    public function test_each_request_gets_a_different_id_even_with_the_same_client_header(): void
    {
        $clientId = '11111111-1111-4111-8111-111111111111';
        $this->withHeader('X-Correlation-ID', $clientId);

        $first = $this->getJson('/api/ping')->assertOk()->headers->get('X-Correlation-ID');
        $second = $this->getJson('/api/ping')->assertOk()->headers->get('X-Correlation-ID');

        $this->assertTrue(Str::isUuid($first));
        $this->assertTrue(Str::isUuid($second));
        $this->assertNotSame($clientId, $first);
        $this->assertNotSame($clientId, $second);
        $this->assertNotSame($first, $second);
    }

    #[DataProvider('errorStatuses')]
    public function test_rendered_errors_keep_the_request_id(int $status): void
    {
        $requestId = null;
        Route::get('/api/correlation-error', function (Request $request) use ($status, &$requestId) {
            $requestId = $request->attributes->get('correlation_id');
            abort($status);
        });

        $response = $this->getJson('/api/correlation-error')->assertStatus($status);

        $this->assertTrue(Str::isUuid($requestId));
        $response->assertHeader('X-Correlation-ID', $requestId);
    }

    public function test_early_request_rejections_have_a_correlation_id(): void
    {
        $response = $this->call('TRACE', '/api/ping')->assertStatus(405);

        $this->assertTrue(Str::isUuid($response->headers->get('X-Correlation-ID')));
    }

    public static function errorStatuses(): array
    {
        return [
            'unauthenticated' => [401],
            'forbidden' => [403],
            'missing' => [404],
            'server error' => [500],
        ];
    }
}
