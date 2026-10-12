<?php

namespace Tests\Feature;

use App\Services\AuditLogRetention;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class AuditLogRetentionTest extends TestCase
{
    public function test_cleanup_respects_calendar_boundary_and_preserves_unrelated_files(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 11)->startOfDay());
        $directory = storage_path('framework/testing/audit-retention-'.Str::uuid());
        File::ensureDirectoryExists($directory);
        config([
            'security_audit.retention_days' => 3,
            'logging.channels.security_audit.path' => $directory.'/security-audit.log',
        ]);
        $names = [
            'security-audit-2026-10-08.log', 'security-audit-2025-01-01.log',
            'security-audit-2026-10-09.log', 'security-audit-2026-10-10.log',
            'security-audit-2026-10-11.log', 'security-audit-2026-10-12.log',
            'security-audit-2026-02-30.log', 'security-audit.log',
            'laravel-2025-01-01.log', 'security-audit-2025-01-01.log.bak',
        ];
        foreach ($names as $name) {
            File::put($directory.'/'.$name, 'fixture');
        }
        mkdir($directory.'/security-audit-2024-01-01.log');

        try {
            $this->artisan('security:audit-prune', ['--dry-run' => true])
                ->expectsOutput('Archivos vencidos encontrados: 2.')->assertExitCode(0);
            foreach ($names as $name) {
                $this->assertFileExists($directory.'/'.$name);
            }
            $this->artisan('security:audit-prune')
                ->expectsOutput('Archivos vencidos eliminados: 2.')->assertExitCode(0);
            foreach (array_slice($names, 0, 2) as $name) {
                $this->assertFileDoesNotExist($directory.'/'.$name);
            }
            foreach (array_slice($names, 2) as $name) {
                $this->assertSame('fixture', File::get($directory.'/'.$name));
            }
            $this->assertDirectoryExists($directory.'/security-audit-2024-01-01.log');
            $this->assertSame(0, app(AuditLogRetention::class)->prune());
        } finally {
            foreach ($names as $name) {
                File::delete($directory.'/'.$name);
            }
            rmdir($directory.'/security-audit-2024-01-01.log');
            rmdir($directory);
            $this->travelBack();
        }
    }

    public function test_invalid_retention_is_rejected_instead_of_disabling_limits(): void
    {
        foreach ([0, -1, 366, 'invalid', '1.5', null] as $days) {
            config(['security_audit.retention_days' => $days]);
            try {
                app(AuditLogRetention::class)->days();
                $this->fail('Se acepto una retencion invalida.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('entre 1 y 365', $exception->getMessage());
            }
        }
    }

    public function test_valid_limits_and_missing_directory_are_supported(): void
    {
        foreach ([1, '90', 365] as $days) {
            config(['security_audit.retention_days' => $days]);
            $this->assertSame((int) $days, app(AuditLogRetention::class)->days());
        }
        config(['logging.channels.security_audit.path' => storage_path('framework/testing/'.Str::uuid().'/audit.log')]);
        $this->assertSame(0, app(AuditLogRetention::class)->prune());
    }

    public function test_cleanup_is_scheduled_daily_without_overlapping(): void
    {
        $this->artisan('list')->assertExitCode(0);
        $events = array_values(array_filter(app(Schedule::class)->events(),
            fn ($event) => str_contains($event->command ?? '', 'security:audit-prune')));
        $this->assertCount(1, $events);
        $this->assertSame('0 2 * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }
}
