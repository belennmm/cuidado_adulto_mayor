<?php

namespace Tests\Feature;

use App\Services\SecurityEventRecorder;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityEventRecorderTest extends TestCase
{
    #[DataProvider('eventContexts')]
    public function test_every_event_includes_the_common_context(
        ?int $actorId,
        ?string $resourceType,
        int|string|null $resourceId,
        string $result,
        ?string $ip,
        ?string $correlationId,
    ): void {
        $request = Request::create('/api/example', 'GET', [], [], [], array_filter([
            'REMOTE_ADDR' => $ip,
            'HTTP_X_CORRELATION_ID' => 'untrusted-client-value',
        ], fn ($value) => $value !== null));
        if ($ip === null) {
            $request->server->remove('REMOTE_ADDR');
        }
        if ($correlationId !== null) {
            $request->attributes->set('correlation_id', $correlationId);
        }

        $records = [];
        Log::shouldReceive('info')->once()->andReturnUsing(function (string $message, array $context) use (&$records) {
            $this->assertSame('security.event', $message);
            $records[] = $context;
        });
        $before = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        app(SecurityEventRecorder::class)->record(
            request: $request,
            event: 'security.example',
            action: 'read',
            result: $result,
            actorId: $actorId,
            resourceType: $resourceType,
            resourceId: $resourceId,
        );

        $after = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->assertCount(1, $records);
        $record = $records[0];
        $this->assertSame([
            'schema_version', 'event', 'action', 'result', 'actor_id', 'resource_type',
            'resource_id', 'occurred_at', 'ip_address', 'correlation_id',
        ], array_keys($record));
        $this->assertSame(1, $record['schema_version']);
        $this->assertSame('security.example', $record['event']);
        $this->assertSame('read', $record['action']);
        $this->assertSame($result, $record['result']);
        $this->assertSame($actorId, $record['actor_id']);
        $this->assertSame($resourceType, $record['resource_type']);
        $this->assertSame($resourceId, $record['resource_id']);
        $this->assertSame($ip, $record['ip_address']);
        $this->assertSame($correlationId, $record['correlation_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $record['occurred_at']);
        $timestamp = new DateTimeImmutable($record['occurred_at']);
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    public static function eventContexts(): array
    {
        return [
            'authenticated IPv4 access' => [42, 'older_adult', 10, 'success', '192.0.2.10', 'server-request-1'],
            'anonymous IPv6 denial' => [null, 'route', 'api/admin/users', 'denied', '2001:db8::10', 'server-request-2'],
            'unknown context remains explicit' => [null, null, null, 'blocked', null, null],
        ];
    }
}
