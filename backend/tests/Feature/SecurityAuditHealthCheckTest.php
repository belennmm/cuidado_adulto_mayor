<?php

namespace Tests\Feature;

use App\Support\AuditRecordIntegrity;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityAuditHealthCheckTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/audit-health-'.Str::uuid());
        File::ensureDirectoryExists($this->directory);
        config(['logging.channels.security_audit.path' => $this->directory.'/audit.log', 'security_audit.collector_url' => null]);
        Log::forgetChannel('security_audit');
    }

    protected function tearDown(): void
    {
        try {
            Log::channel('security_audit')->getLogger()->close();
            Log::forgetChannel('security_audit');
            foreach (File::files($this->directory) as $file) {
                File::delete($file->getPathname());
            }
            rmdir($this->directory);
        } finally {
            parent::tearDown();
        }
    }

    public function test_health_command_proves_a_new_signed_event_was_persisted(): void
    {
        $this->artisan('security:audit-health')->assertExitCode(0);
        $path = Log::channel('security_audit')->getLogger()->getHandlers()[0]->getUrl();
        $records = array_map(fn ($line) => json_decode($line, true), file($path, FILE_IGNORE_NEW_LINES));
        $this->assertCount(1, $records);
        $this->assertSame('security.audit.health', $records[0]['context']['event']);
        $this->assertStringEndsWith('Z', $records[0]['context']['occurred_at']);
        $this->assertNotEmpty($records[0]['context']['correlation_id']);
        $this->assertTrue(app(AuditRecordIntegrity::class)->verify($records[0]));
        $this->artisan('security:audit-health')->assertExitCode(0);
        $this->assertCount(2, file($path, FILE_IGNORE_NEW_LINES));
    }

    public function test_health_fails_when_quota_prevents_persisting_the_new_event(): void
    {
        config(['security_audit.limits.events_per_minute' => 1]);
        Log::forgetChannel('security_audit');
        $this->artisan('security:audit-health')->assertExitCode(0);
        $this->artisan('security:audit-health')->assertExitCode(1);
    }

    public function test_health_is_scheduled_every_five_minutes_without_overlapping(): void
    {
        $events = array_values(array_filter(app(Schedule::class)->events(), fn ($event) => str_contains($event->command ?? '', 'security:audit-health')));
        $this->assertCount(1, $events);
        $this->assertSame('*/5 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }
}
