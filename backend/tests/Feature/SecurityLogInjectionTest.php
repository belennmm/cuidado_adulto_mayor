<?php

namespace Tests\Feature;

use App\Services\SecurityEventRecorder;
use App\Support\AuditRecordIntegrity;
use App\Support\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

class SecurityLogInjectionTest extends TestCase
{
    public function test_recorder_and_direct_channel_writes_cannot_forge_lines_or_bypass_sanitization(): void
    {
        $directory = storage_path('framework/testing/log-injection-'.Str::uuid());
        File::ensureDirectoryExists($directory);
        config([
            'logging.channels.security_audit.path' => $directory.'/audit.log',
            'security_audit.collector_url' => null,
        ]);
        Log::forgetChannel('security_audit');
        $logger = Log::channel('security_audit')->getLogger();
        $path = $logger->getHandlers()[0]->getUrl();
        $payload = "\r\n{\"message\":\"forged\"}\x1b[2J\u{2028}\xc3\x28";

        try {
            $request = Request::create('/api/example');
            $request->attributes->set('correlation_id', $payload);
            app(SecurityEventRecorder::class)->record($request, 'security.example'.$payload, 'read', 'success', 42, 'route', 'api/example'.$payload);
            $context = (new SecurityEvent('security.example', 'read', 'success', actorId: 42))->toArray();
            $logger->addRecord(Level::Info, 'security.event'.$payload, [
                ...$context, 'actor_id' => $payload, 'occurred_at' => $payload,
                'notes' => ['private-clinical-content'.$payload],
            ]);
            $logger->addRecord(Level::Warning, 'security.alert', $context);
            $logger->pushProcessor(fn (LogRecord $record) => $record->with(extra: ['private' => 'private-extra-content'.$payload]));
            $logger->addRecord(Level::Info, 'security.event', $context);

            $content = File::get($path);
            $this->assertSame(4, substr_count($content, "\n"));
            $this->assertDoesNotMatchRegularExpression('/[\x00-\x09\x0B-\x1F\x7F]/', $content);
            $this->assertStringNotContainsString('forged', $content);
            $this->assertStringNotContainsString('private-clinical-content', $content);
            $this->assertStringNotContainsString('private-extra-content', $content);
            $this->assertStringNotContainsString("\u{2028}", $content);
            $records = array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), explode("\n", rtrim($content, "\n")));
            foreach ($records as $record) {
                $this->assertTrue(app(AuditRecordIntegrity::class)->verify($record));
                $this->assertCount(10, $record['context']);
                $this->assertSame(['audit_integrity'], array_keys($record['extra']));
            }
            $this->assertNull($records[0]['context']['event']);
            $this->assertNull($records[0]['context']['correlation_id']);
            $this->assertNull($records[0]['context']['resource_id']);
            $this->assertNull($records[1]['context']['occurred_at']);
            $this->assertSame('security.event', $records[1]['message']);
            $this->assertSame('security.alert', $records[2]['message']);
            $this->artisan('security:audit-verify', ['path' => $path])->assertExitCode(0);
        } finally {
            $logger->close();
            Log::forgetChannel('security_audit');
            File::delete($path);
            File::delete($directory.'/audit.log.budget.lock');
            rmdir($directory);
        }
    }
}
