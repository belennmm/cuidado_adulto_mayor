<?php

namespace Tests\NonFunctional\Reliability;

use App\Models\Incident;
use App\Models\OlderAdult;
use App\Models\User;
use App\Services\IncidentListingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** SCRUM-1020/1021: fallos controlados y contrato HTTP de sus respuestas. */
class ApiServerErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('serverFailures')]
    public function test_500_response_contract_is_stable_for_each_failure_and_accept_header(
        string $failure,
        bool $debug,
        ?string $accept,
    ): void {
        config(['app.debug' => $debug]);
        // Ruta registrada sólo en la aplicación efímera de PHPUnit.
        Route::get('/api/test-only/server-failure', function () use ($failure) {
            return match ($failure) {
                'runtime' => throw new RuntimeException('Fallo sintético SCRUM-1020'),
                'database' => DB::select('SELECT synthetic_column FROM synthetic_missing_table'),
                'type' => strlen([]),
                'http' => abort(500, 'Fallo sintético HTTP'),
                'response-exception' => throw new HttpResponseException(response('<h1>Fallo sintético</h1>', 500, ['Content-Type' => 'text/html', 'Content-Length' => '9999'])),
                'direct' => response('<h1>Fallo sintético</h1>', 500, ['Content-Type' => 'text/html', 'Content-Length' => '9999']),
            };
        });

        $headers = $accept === null ? [] : ['Accept' => $accept];
        $this->assertSafeServerResponse($this->get('/api/test-only/server-failure', $headers));
        $this->getJson('/api/ping')->assertOk();
    }

    public static function serverFailures(): iterable
    {
        foreach (['runtime', 'database', 'type', 'http', 'response-exception', 'direct'] as $failure) {
            foreach ([false, true] as $debug) {
                foreach (['application/json', 'text/html', null] as $accept) {
                    yield $failure.'-debug-'.(int) $debug.'-'.($accept ?? 'no-accept') => [$failure, $debug, $accept];
                }
            }
        }
    }

    #[DataProvider('incidentFailures')]
    public function test_actual_incident_endpoint_preserves_data_and_error_contract_on_service_failure(
        string $role,
        string $failure,
    ): void {
        $user = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $adult = OlderAdult::factory()->create([
            'created_by' => $user->id,
            'professional_caregiver_id' => $role === 'profesional' ? $user->id : null,
            'family_caregiver_id' => $role === 'familiar' ? $user->id : null,
        ]);
        $incident = Incident::create([
            'older_adult_id' => $adult->id, 'adult_name' => $adult->full_name,
            'reported_by' => $user->id, 'title' => 'Incidente sintético previo al fallo',
            'description' => 'Registro que debe conservarse', 'severity' => 'media', 'status' => 'abierto',
            'incident_date' => '2026-10-20',
        ]);
        $original = DB::table('incidents')->where('id', $incident->id)->first();
        Sanctum::actingAs($user);
        $exception = $failure === 'database'
            ? new QueryException('sqlite', 'SELECT synthetic_column FROM incidents', [], new RuntimeException('Fallo de consulta simulado'))
            : new RuntimeException('Fallo de listado simulado');
        $this->mock(IncidentListingService::class)->shouldReceive('forDate')->once()->andThrow($exception);

        $this->assertSafeServerResponse($this->getJson('/api/incidents?date=2026-10-20'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('older_adults', 1);
        $this->assertDatabaseCount('incidents', 1);
        $this->assertEquals($original, DB::table('incidents')->where('id', $incident->id)->first());
    }

    public static function incidentFailures(): iterable
    {
        foreach (['admin', 'profesional', 'familiar'] as $role) {
            foreach (['runtime', 'database'] as $failure) {
                yield $role.'-'.$failure => [$role, $failure];
            }
        }
    }

    public function test_independent_failures_receive_distinct_server_generated_correlation_ids(): void
    {
        Route::get('/api/test-only/repeated-failure', fn () => abort(500));
        $clientId = '11111111-1111-4111-8111-111111111111';
        $first = $this->getJson('/api/test-only/repeated-failure', ['X-Correlation-ID' => $clientId]);
        $second = $this->getJson('/api/test-only/repeated-failure', ['X-Correlation-ID' => $clientId]);
        $this->assertSafeServerResponse($first);
        $this->assertSafeServerResponse($second);
        $this->assertNotSame($clientId, $first->headers->get('X-Correlation-ID'));
        $this->assertNotSame($first->headers->get('X-Correlation-ID'), $second->headers->get('X-Correlation-ID'));
    }

    private function assertSafeServerResponse(TestResponse $response): void
    {
        $response->assertStatus(500)
            ->assertExactJson(['message' => 'Error interno del servidor.'])
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertTrue(Str::isUuid($response->headers->get('X-Correlation-ID')));
        $contentLength = $response->headers->get('Content-Length');
        if ($contentLength !== null) {
            $this->assertSame((string) strlen($response->getContent()), $contentLength);
        }
    }
}
