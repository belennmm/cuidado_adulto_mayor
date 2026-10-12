<?php

namespace Tests\Feature;

use App\Services\SecurityEventRecorder;
use App\Support\AuditRecordIntegrity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Formatter\JsonFormatter;
use Tests\TestCase;

class SecurityAuditChannelTest extends TestCase
{
    public function test_audit_channel_has_an_independent_path_and_json_formatter(): void
    {
        $audit = config('logging.channels.security_audit');

        $this->assertSame('daily', $audit['driver']);
        $this->assertSame(90, $audit['days']);
        $this->assertSame(storage_path('logs/security-audit.log'), $audit['path']);
        $this->assertNotSame(config('logging.channels.single.path'), $audit['path']);
        $this->assertSame(JsonFormatter::class, $audit['formatter']);
        $this->assertTrue($audit['locking']);
        $this->assertSame(0600, $audit['permission']);
        $this->assertSame('info', $audit['level']);
    }

    public function test_real_writes_keep_operational_messages_separate_from_audit_events(): void
    {
        $directory = storage_path('framework/testing/audit-channel-'.Str::uuid());
        File::ensureDirectoryExists($directory);
        $auditPath = $directory.'/audit.log';
        $operationalPath = $directory.'/operational.log';
        config([
            'logging.default' => 'audit_test_operational',
            'logging.channels.audit_test_operational' => [
                'driver' => 'single', 'path' => $operationalPath, 'level' => 'debug',
            ],
            'logging.channels.security_audit.path' => $auditPath,
            'security_audit.collector_url' => null,
        ]);
        Log::forgetChannel('security_audit');

        try {
            Log::info('operational-only-marker');
            $request = Request::create('/api/example', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.123']);
            $request->attributes->set('correlation_id', '11111111-1111-4111-8111-111111111111');
            app(SecurityEventRecorder::class)->record($request, 'security.example', 'read', 'success', 42, 'older_adult', 10);

            $basePath = $auditPath;
            $auditPath = Log::channel('security_audit')->getLogger()->getHandlers()[0]->getUrl();
            $this->assertNotSame($basePath, $auditPath);
            $this->assertMatchesRegularExpression('/audit-\d{4}-\d{2}-\d{2}\.log$/', $auditPath);
            $this->artisan('security:audit-verify')->assertExitCode(0);

            $this->assertFileExists($auditPath);
            $this->assertFileExists($operationalPath);
            $auditContent = File::get($auditPath);
            $operationalContent = File::get($operationalPath);
            $this->assertStringNotContainsString('operational-only-marker', $auditContent);
            $this->assertStringNotContainsString('security.event', $operationalContent);
            $this->assertStringContainsString('operational-only-marker', $operationalContent);

            $lines = array_values(array_filter(explode("\n", trim($auditContent))));
            $this->assertCount(1, $lines);
            $record = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('security.event', $record['message']);
            $this->assertSame('INFO', $record['level_name']);
            $this->assertSame('security_audit', $record['channel']);
            $this->assertSame('security.example', $record['context']['event']);
            $this->assertSame('192.0.2.0', $record['context']['ip_address']);
            $this->assertSame(42, $record['context']['actor_id']);
            $this->assertTrue(app(AuditRecordIntegrity::class)->verify($record));
            $this->artisan('security:audit-verify', ['path' => $auditPath])->assertExitCode(0);
            $record['context']['actor_id'] = 99;
            File::put($auditPath, json_encode($record, JSON_THROW_ON_ERROR)."\n");
            $this->artisan('security:audit-verify', ['path' => $auditPath])->assertExitCode(1);
        } finally {
            foreach (['security_audit', 'audit_test_operational'] as $channel) {
                Log::channel($channel)->getLogger()->close();
                Log::forgetChannel($channel);
            }
            File::delete([$auditPath, $operationalPath]);
            File::delete($basePath.'.budget.lock');
            rmdir($directory);
        }
    }
}
