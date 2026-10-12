<?php

namespace Tests\Unit;

use App\Support\SecurityEvent;
use App\Support\SecurityEventPrivacy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SecurityLogInjectionTest extends TestCase
{
    #[DataProvider('maliciousValues')]
    public function test_controls_and_invalid_utf8_are_removed_from_every_string_field(string $payload): void
    {
        $context = (new SecurityEvent('security.example', 'read', 'success'))->toArray();
        foreach (['event', 'action', 'result', 'resource_type', 'resource_id', 'actor_id', 'occurred_at', 'ip_address', 'correlation_id'] as $field) {
            $filtered = SecurityEventPrivacy::filter([...$context, $field => $payload]);
            $this->assertNull($filtered[$field], $field);
            $this->assertCount(10, $filtered);
        }
    }

    public static function maliciousValues(): array
    {
        return [
            'forged JSON line' => ["value\r\n{\"message\":\"forged\"}"],
            'LF' => ["value\n"],
            'CR' => ["value\r"],
            'tab' => ["value\t"],
            'null byte' => ["value\0"],
            'terminal escape' => ["\x1b[2Jvalue"],
            'delete control' => ["value\x7f"],
            'C1 next line' => ["value\u{0085}"],
            'Unicode line separator' => ["value\u{2028}"],
            'Unicode paragraph separator' => ["value\u{2029}"],
            'bidirectional override' => ["value\u{202E}"],
            'bidirectional isolate' => ["value\u{2066}"],
            'invalid UTF8' => ["value\xc3\x28"],
        ];
    }

    public function test_invalid_dates_and_text_actor_ids_cannot_bypass_the_schema(): void
    {
        $context = (new SecurityEvent('security.example', 'read', 'success'))->toArray();
        $filtered = SecurityEventPrivacy::filter([
            ...$context, 'schema_version' => "1\nforged", 'actor_id' => 'forged',
            'occurred_at' => '2026-02-30T12:00:00.000000Z',
        ]);
        $this->assertSame(1, $filtered['schema_version']);
        $this->assertNull($filtered['actor_id']);
        $this->assertNull($filtered['occurred_at']);
        $this->assertSame($filtered, SecurityEventPrivacy::filter($filtered));
    }
}
