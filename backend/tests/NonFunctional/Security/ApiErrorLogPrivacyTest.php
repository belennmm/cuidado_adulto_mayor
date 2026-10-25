<?php

namespace Tests\NonFunctional\Security;

use App\Support\AuditRecordIntegrity;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Monolog\Formatter\JsonFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** SCRUM-1022/1023: inspección de archivos reales, sin usar logs de usuarios. */
class ApiErrorLogPrivacyTest extends TestCase
{
    #[DataProvider('failures')]
    public function test_response_and_real_logs_exclude_sensitive_error_details(
        string $failure,
        bool $debug,
        string $accept,
    ): void {
        $directory = storage_path('framework/testing/api-error-privacy-'.Str::uuid());
        File::ensureDirectoryExists($directory);
        $operationalPath = $directory.'/operational.log';
        config([
            'app.debug' => $debug,
            'logging.default' => 'single',
            'logging.channels.single.path' => $operationalPath,
            'logging.channels.single.formatter' => JsonFormatter::class,
            'logging.channels.security_audit.path' => $directory.'/audit.log',
            'security_audit.collector_url' => null,
        ]);
        foreach (['stack', 'single', 'security_audit'] as $channel) {
            Log::forgetChannel($channel);
        }
        $markers = [
            'PATIENT_CANARY_1023', 'CLINICAL_DIAGNOSIS_CANARY_1023',
            'SYNTHETIC_TOKEN_CANARY_1023', 'SYNTHETIC_PASSWORD_CANARY_1023',
            'SELECT clinical_notes FROM synthetic_records', 'SQLSTATE_CANARY_1023',
            '/srv/care/internal/canary-1023.php', 'TRACE_CANARY_1023', 'FORGED_EVENT_CANARY_1023',
        ];
        $message = implode("\r\n", $markers);
        Route::post('/api/test-only/private-failure', function () use ($failure, $message) {
            return match ($failure) {
                'runtime' => throw new RuntimeException($message),
                'database' => throw new QueryException('sqlite', 'SELECT clinical_notes FROM synthetic_records WHERE note = ?', [$message], new RuntimeException($message)),
                'storage' => throw new FileNotFoundException($message),
                'anonymous' => throw new class($message) extends RuntimeException {},
                'http' => abort(503, $message),
                'response-exception' => throw new HttpResponseException(response()->json(['clinical_notes' => $message, 'trace' => [$message]], 500)),
                'direct' => response($message, 500, ['Content-Type' => 'text/html']),
            };
        });

        try {
            $response = $this->postJson('/api/test-only/private-failure', [
                'full_name' => $markers[0], 'diagnosis' => $markers[1],
                'notes' => $message, 'password' => $markers[3],
            ], [
                'Accept' => $accept, 'Authorization' => 'Bearer '.$markers[2],
                'X-Correlation-ID' => '11111111-1111-4111-8111-111111111111',
            ])->assertStatus($failure === 'http' ? 503 : 500)
                ->assertExactJson([
                    'code' => $failure === 'http' ? 'service_unavailable' : 'internal_server_error',
                    'message' => 'Error interno del servidor.', 'errors' => [],
                ])
                ->assertHeader('Content-Type', 'application/json');
            $correlationId = $response->headers->get('X-Correlation-ID');
            $this->assertTrue(Str::isUuid($correlationId));
            $this->assertNotSame('11111111-1111-4111-8111-111111111111', $correlationId);
            $auditPath = Log::channel('security_audit')->getLogger()->getHandlers()[0]->getUrl();
            $this->assertFileExists($auditPath);
            $auditContent = File::get($auditPath);
            $operationalContent = File::exists($operationalPath) ? File::get($operationalPath) : '';
            $allOutput = $response->getContent().json_encode($response->headers->all(), JSON_THROW_ON_ERROR)
                .$auditContent.$operationalContent;
            foreach ($markers as $marker) {
                $this->assertStringNotContainsString($marker, $allOutput);
            }
            $this->assertStringNotContainsString('ApiErrorLogPrivacyTest.php', $allOutput);
            $this->assertStringNotContainsString('@anonymous', $allOutput);

            $operationalRecords = $this->records($operationalContent);
            if (in_array($failure, ['runtime', 'database', 'storage', 'anonymous'], true)) {
                $this->assertCount(1, $operationalRecords);
                $record = $operationalRecords[0];
                $this->assertSame('API request failed.', $record['message']);
                $this->assertSame('ERROR', $record['level_name']);
                $this->assertEqualsCanonicalizing(['exception_type', 'correlation_id'], array_keys($record['context']));
                $this->assertSame($correlationId, $record['context']['correlation_id']);
                $this->assertSame(match ($failure) {
                    'database' => QueryException::class,
                    'storage' => FileNotFoundException::class,
                    default => RuntimeException::class,
                }, $record['context']['exception_type']);
            } else {
                // Laravel no reporta como excepciones estas respuestas; sí se auditan.
                $this->assertCount(0, $operationalRecords);
            }
            $auditRecords = $this->records($auditContent);
            $this->assertNotEmpty($auditRecords);
            $events = [];
            foreach ($auditRecords as $record) {
                $this->assertSame('security.event', $record['message']);
                $this->assertSame('ERROR', $record['level_name']);
                $this->assertSame('failure', $record['context']['result']);
                $this->assertSame($correlationId, $record['context']['correlation_id']);
                $this->assertTrue(app(AuditRecordIntegrity::class)->verify($record));
                $events[] = $record['context']['event'];
            }
            $expectedEvents = ['infrastructure.application'];
            if (in_array($failure, ['database', 'storage'], true)) {
                $expectedEvents[] = 'infrastructure.'.$failure;
            }
            $this->assertEqualsCanonicalizing($expectedEvents, $events);
        } finally {
            foreach (['single', 'security_audit'] as $channel) {
                Log::channel($channel)->getLogger()->close();
                Log::forgetChannel($channel);
            }
            File::deleteDirectory($directory);
        }
    }

    private function records(string $content): array
    {
        $lines = array_filter(explode("\n", trim($content)), fn (string $line) => $line !== '');

        return array_values(array_map(fn (string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines));
    }

    public static function failures(): iterable
    {
        foreach (['runtime', 'database', 'storage', 'anonymous', 'http', 'response-exception', 'direct'] as $failure) {
            foreach ([false, true] as $debug) {
                foreach (['application/json', 'text/html'] as $accept) {
                    yield $failure.'-debug-'.(int) $debug.'-'.$accept => [$failure, $debug, $accept];
                }
            }
        }
    }
}
