<?php

namespace Tests\Unit;

use App\Support\SecurityEvent;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SecurityEventTest extends TestCase
{
    public function test_it_serializes_the_common_schema_and_converts_dates_to_utc(): void
    {
        $event = new SecurityEvent(
            event: 'authentication.login',
            action: 'login',
            result: 'denied',
            actorId: 42,
            resourceType: 'user',
            resourceId: 42,
            ipAddress: '192.0.2.10',
            correlationId: 'request-example',
            occurredAt: new DateTimeImmutable('2026-10-11T20:50:00-06:00'),
        );

        $expected = [
            'schema_version' => 1,
            'event' => 'authentication.login',
            'action' => 'login',
            'result' => 'denied',
            'actor_id' => 42,
            'resource_type' => 'user',
            'resource_id' => 42,
            'occurred_at' => '2026-10-12T02:50:00.000000Z',
            'ip_address' => '192.0.2.10',
            'correlation_id' => 'request-example',
        ];

        $this->assertSame($expected, $event->toArray());
        $this->assertSame($expected, json_decode(json_encode($event, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_an_anonymous_event_keeps_the_same_schema_and_generates_a_timestamp(): void
    {
        $before = new DateTimeImmutable();
        $event = new SecurityEvent('authentication.login', 'login', 'denied');
        $after = new DateTimeImmutable();
        $data = $event->toArray();

        foreach (['actor_id', 'resource_type', 'resource_id', 'ip_address', 'correlation_id'] as $field) {
            $this->assertArrayHasKey($field, $data);
            $this->assertNull($data[$field]);
        }

        $this->assertGreaterThanOrEqual($before, $event->occurredAt);
        $this->assertLessThanOrEqual($after, $event->occurredAt);
        $this->assertSame('UTC', $event->occurredAt->getTimezone()->getName());
    }

    #[DataProvider('emptyRequiredFields')]
    public function test_it_rejects_empty_required_fields(string $event, string $action, string $result): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SecurityEvent($event, $action, $result);
    }

    public static function emptyRequiredFields(): array
    {
        return [
            'empty event' => ['', 'login', 'denied'],
            'blank action' => ['authentication.login', '   ', 'denied'],
            'empty result' => ['authentication.login', 'login', ''],
        ];
    }
}
