<?php

namespace Tests\Unit;

use App\Support\SecurityEvent;
use App\Support\SecurityEventPrivacy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SecurityEventPersonalDataMaskingTest extends TestCase
{
    #[DataProvider('addresses')]
    public function test_it_masks_the_host_part_of_valid_ip_addresses(string $ip, string $expected): void
    {
        $context = (new SecurityEvent('security.example', 'read', 'success', ipAddress: $ip))->toArray();

        $filtered = SecurityEventPrivacy::filter($context);

        $this->assertSame($expected, $filtered['ip_address']);
        $this->assertSame($ip, $context['ip_address']);
        $this->assertNotFalse(filter_var($filtered['ip_address'], FILTER_VALIDATE_IP));
        $this->assertSame($filtered, SecurityEventPrivacy::filter($filtered));
    }

    public function test_masking_keeps_the_identifiers_needed_to_investigate_the_event(): void
    {
        $context = (new SecurityEvent(
            event: 'sensitive_data.access', action: 'read', result: 'success',
            actorId: 42, resourceType: 'older_adult', resourceId: 10,
            ipAddress: '192.0.2.123', correlationId: '11111111-1111-4111-8111-111111111111',
        ))->toArray();

        $filtered = SecurityEventPrivacy::filter($context);

        $this->assertSame('192.0.2.0', $filtered['ip_address']);
        foreach (['actor_id', 'resource_type', 'resource_id', 'correlation_id', 'occurred_at', 'action', 'result'] as $field) {
            $this->assertSame($context[$field], $filtered[$field]);
        }
    }

    public static function addresses(): array
    {
        return [
            'IPv4 host' => ['192.0.2.123', '192.0.2.0'],
            'another IPv4 network' => ['198.51.100.42', '198.51.100.0'],
            'loopback' => ['127.0.0.1', '127.0.0.0'],
            'IPv6 host' => ['2001:db8:abcd:1234:5678:90ab:cdef:1234', '2001:db8:abcd::'],
            'compressed IPv6' => ['2001:db8::1234', '2001:db8::'],
            'IPv4 mapped into IPv6' => ['::ffff:192.0.2.123', '192.0.2.0'],
            'hexadecimal mapped IPv4' => ['::ffff:c000:027b', '192.0.2.0'],
        ];
    }
}
