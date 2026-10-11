<?php

namespace Tests\Feature;

use App\Logging\RedactSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
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
        foreach (config('logging.channels') as $channel) {
            $this->assertContains(RedactSecrets::class, $channel['tap']);
        }
    }

    public function test_api_exception_reporting_omits_raw_exception_and_request_secrets(): void
    {
        Route::get('api/security-secret-test', function () {
            throw new \RuntimeException('password=raw-password code=123456 SQL SELECT secret');
        });
        Log::spy();
        $response = $this->getJson('/api/security-secret-test')->assertStatus(500);
        $this->assertSame(['message' => 'Error interno del servidor.'], $response->json());
        Log::shouldHaveReceived('error')->once()->with('API request failed.', ['exception_type' => \RuntimeException::class]);
    }
}
