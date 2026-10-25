<?php

namespace Tests\Feature;

use App\Logging\RedactSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Tests\TestCase;

class AuthenticationSecretProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_credentials_are_rejected_without_echoing_values(): void
    {
        foreach (['password', 'token', 'code', 'challenge', 'mfa_secret', 'recovery_codes', 'Authorization'] as $key) {
            $response = $this->getJson('/api/ping?'.$key.'=do-not-leak')->assertUnprocessable();
            $this->assertStringNotContainsString('do-not-leak', $response->getContent());
        }
    }

    public function test_logging_processor_redacts_nested_context_bearer_tokens_and_exception_objects(): void
    {
        $handler = new TestHandler;
        $logger = new Logger(new Monolog('security', [$handler]));
        (new RedactSecrets)($logger);
        $secret = str_repeat('a', 64);
        $logger->error('Bearer sensitive-value token='.$secret.' code=123456', [
            'password' => 'Sensitive password with spaces',
            'nested' => ['recovery_codes' => ['backup-value'], 'headers' => ['Cookie' => 'session-value']],
            'exception' => new \RuntimeException('raw SQL '.$secret),
        ]);
        $record = $handler->getRecords()[0];
        $encoded = json_encode([$record->message, $record->context]);
        foreach (['sensitive-value', $secret, '123456', 'Sensitive password', 'backup-value', 'session-value', 'raw SQL'] as $value) {
            $this->assertStringNotContainsString($value, $encoded);
        }
        $this->assertStringContainsString('[REDACTED]', $encoded);
        $this->assertSame('password=[REDACTED]', RedactSecrets::text('password="Sensitive password with spaces"'));
        $this->assertConfiguredChannelsRedactSecrets();
    }

    public function test_logging_processor_redacts_clinical_fields_at_any_depth(): void
    {
        $handler = new TestHandler;
        $logger = new Logger(new Monolog('clinical-privacy', [$handler]));
        (new RedactSecrets)($logger);
        $clinicalValues = [
            'diagnosis' => 'DIAGNOSIS_CANARY',
            'medical_history' => 'HISTORY_CANARY',
            'allergies' => 'ALLERGY_CANARY',
            'medications' => [['name' => 'MEDICATION_CANARY', 'dosage' => 'DOSAGE_CANARY']],
            'care' => ['notes' => 'NOTES_CANARY', 'observations' => 'OBSERVATION_CANARY'],
        ];

        $logger->info('Clinical operation completed.', ['patient_id' => 42, 'record' => $clinicalValues]);

        $record = $handler->getRecords()[0];
        $encoded = json_encode($record->context, JSON_THROW_ON_ERROR);
        foreach (['DIAGNOSIS_CANARY', 'HISTORY_CANARY', 'ALLERGY_CANARY', 'MEDICATION_CANARY',
            'DOSAGE_CANARY', 'NOTES_CANARY', 'OBSERVATION_CANARY'] as $value) {
            $this->assertStringNotContainsString($value, $encoded);
        }
        $this->assertSame(42, $record->context['patient_id']);
        $this->assertSame('[REDACTED]', $record->context['record']['diagnosis']);
        $this->assertSame('[REDACTED]', $record->context['record']['medications']);
    }

    public function test_framework_channels_without_taps_do_not_break_configured_channel_checks(): void
    {
        config(['logging.channels.framework-diagnostic' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ]]);

        $this->assertConfiguredChannelsRedactSecrets();
        $this->assertArrayNotHasKey('tap', config('logging.channels.framework-diagnostic'));
    }

    private function assertConfiguredChannelsRedactSecrets(): void
    {
        // Frameworks and packages may add runtime channels outside our configuration contract.
        $configured = require config_path('logging.php');
        foreach (array_keys($configured['channels']) as $name) {
            $this->assertContains(RedactSecrets::class, config("logging.channels.{$name}.tap", []),
                "Configured log channel [{$name}] must redact secrets.");
        }
    }

    public function test_api_exception_reporting_omits_raw_exception_and_request_secrets(): void
    {
        Route::get('api/security-secret-test', function () {
            throw new \RuntimeException('password=raw-password code=123456 SQL SELECT secret');
        });
        Log::spy();
        $response = $this->getJson('/api/security-secret-test')->assertStatus(500);
        $this->assertSame([
            'code' => 'internal_server_error',
            'message' => 'Error interno del servidor.',
            'errors' => [],
        ], $response->json());
        Log::shouldHaveReceived('error')->once()->with('API request failed.', [
            'exception_type' => \RuntimeException::class,
            'correlation_id' => $response->headers->get('X-Correlation-ID'),
        ]);
    }
}
