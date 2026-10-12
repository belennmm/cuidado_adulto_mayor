<?php

namespace Tests\Feature;

use App\Support\AuditLogLimits;
use App\Support\AuditRecordIntegrity;
use App\Support\SecurityEvent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

class AuditLogLimitsTest extends TestCase
{
    private string $directory;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/audit-limits-'.Str::uuid());
        File::ensureDirectoryExists($this->directory);
        config([
            'logging.channels.security_audit.path' => $this->directory.'/audit.log',
            'security_audit.collector_url' => null,
            'security_audit.limits.events_per_minute' => 2,
            'security_audit.limits.urgent_per_minute' => 1,
        ]);
        Log::forgetChannel('security_audit');
        $this->path = Log::channel('security_audit')->getLogger()->getHandlers()[0]->getUrl();
    }

    protected function tearDown(): void
    {
        try {
            Log::channel('security_audit')->getLogger()->close();
            Log::forgetChannel('security_audit');
            File::delete([$this->path, $this->directory.'/audit.log.budget.lock', $this->directory.'/operational.log']);
            rmdir($this->directory);
        } finally {
            parent::tearDown();
        }
    }

    public function test_normal_flood_preserves_a_separate_urgent_budget_and_reports_once(): void
    {
        $this->captureOperationalLog(function (): void {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $this->write('info');
            }
            $this->write('critical');
            $this->write('error');
        });
        $records = $this->records();
        $this->assertCount(3, $records);
        $this->assertSame(['INFO', 'INFO', 'CRITICAL'], array_column($records, 'level_name'));
        $notice = File::get($this->directory.'/operational.log');
        $this->assertSame(1, substr_count($notice, 'security_audit_capacity_limited'));
    }

    public function test_budget_is_shared_when_the_logger_is_recreated(): void
    {
        $this->write('warning');
        Log::channel('security_audit')->getLogger()->close();
        Log::forgetChannel('security_audit');
        $this->write('warning');
        $this->write('warning');
        $this->assertCount(2, $this->records());
    }

    public function test_oversized_records_are_dropped_without_truncating_or_invalidating_other_signatures(): void
    {
        config(['security_audit.limits.record_bytes' => 1024]);
        Log::forgetChannel('security_audit');
        $context = (new SecurityEvent('security.example', 'read', 'success', resourceId: str_repeat('1', 2000)))->toArray();
        Log::channel('security_audit')->info('security.event', $context);
        $this->assertFileDoesNotExist($this->path);
        $this->write('info');
        $this->assertCount(1, $this->records());
    }

    public function test_daily_quota_counts_existing_file_bytes_and_reserves_space_for_urgent_events(): void
    {
        config([
            'security_audit.limits.record_bytes' => 1024,
            'security_audit.limits.daily_bytes' => 8192,
            'security_audit.limits.events_per_minute' => 100,
            'security_audit.limits.urgent_per_minute' => 100,
        ]);
        Log::forgetChannel('security_audit');
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->write('info');
        }
        $normalSize = filesize($this->path);
        $this->assertLessThanOrEqual((int) floor(8192 * 0.9), $normalSize);
        $before = count($this->records());
        Log::channel('security_audit')->getLogger()->close();
        Log::forgetChannel('security_audit');
        $this->write('info');
        $this->assertCount($before, $this->records());
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->write('critical');
        }
        clearstatcache(true, $this->path);
        $this->assertLessThanOrEqual(8192, filesize($this->path));
        $this->assertGreaterThan($before, count($this->records()));
    }

    public function test_invalid_configuration_cannot_disable_limits(): void
    {
        config(['security_audit.limits.events_per_minute' => 0]);
        $this->expectException(InvalidArgumentException::class);
        AuditLogLimits::settings();
    }

    public function test_expired_minute_reopens_the_budget_without_deleting_evidence(): void
    {
        $this->write('info');
        $this->write('info');
        $lockPath = $this->directory.'/audit.log.budget.lock';
        $state = json_decode(File::get($lockPath), true, 8, JSON_THROW_ON_ERROR);
        $state['minute'] = '2000-01-01-00-00';
        File::put($lockPath, json_encode($state, JSON_THROW_ON_ERROR));
        $this->write('info');
        $this->assertCount(3, $this->records());
    }

    public function test_batch_writes_cannot_bypass_the_event_budget(): void
    {
        $record = app(AuditRecordIntegrity::class)(new LogRecord(
            new \DateTimeImmutable(), 'security_audit', Level::Info, 'security.event',
            (new SecurityEvent('security.example', 'read', 'success'))->toArray(),
        ));
        Log::channel('security_audit')->getLogger()->getHandlers()[0]->handleBatch(array_fill(0, 5, $record));
        $this->assertCount(2, $this->records());
    }

    public function test_corrupted_budget_drops_writes_instead_of_disabling_limits(): void
    {
        File::put($this->directory.'/audit.log.budget.lock', 'not-json');
        $this->captureOperationalLog(fn () => $this->write('info'));
        $this->assertFileDoesNotExist($this->path);
        $this->assertStringContainsString('security_audit_budget_unavailable', File::get($this->directory.'/operational.log'));
    }

    private function captureOperationalLog(callable $operation): void
    {
        $previousErrorLog = ini_get('error_log');
        $this->assertNotFalse(ini_set('error_log', $this->directory.'/operational.log'));
        try {
            $operation();
        } finally {
            ini_set('error_log', $previousErrorLog);
        }
    }

    private function write(string $level): void
    {
        Log::channel('security_audit')->log($level, 'security.event',
            (new SecurityEvent('security.example', 'read', 'success', actorId: 42))->toArray());
    }

    private function records(): array
    {
        $lines = explode("\n", rtrim(File::get($this->path), "\n"));
        $records = array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);
        foreach ($records as $record) {
            $this->assertTrue(app(AuditRecordIntegrity::class)->verify($record));
        }

        return $records;
    }
}
