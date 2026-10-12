<?php

namespace Tests\Unit;

use App\Support\SecurityEvent;
use App\Support\SecurityEventPrivacy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SecurityEventPrivacyTest extends TestCase
{
    public function test_extra_fields_are_excluded_even_when_sensitive_data_is_nested(): void
    {
        $event = new SecurityEvent('sensitive_data.access', 'read', 'success', resourceType: 'older_adult', resourceId: 10);
        $context = $event->toArray();
        $extra = [
            'password' => 'private-password', 'token' => 'private-token',
            'cookies' => ['session' => 'private-session'], 'authorization' => 'Bearer private-bearer',
            'medical_history' => 'private-diagnosis', 'notes' => 'private-note',
            'metadata' => ['user' => ['email' => 'private@example.com']],
            'request' => ['headers' => ['Cookie' => 'private-cookie']],
            'response' => ['token' => 'private-response-token'],
        ];

        $filtered = SecurityEventPrivacy::filter([...$context, ...$extra]);

        $this->assertSame($context, $filtered);
        foreach (array_keys($extra) as $field) {
            $this->assertArrayNotHasKey($field, $filtered);
        }
    }

    #[DataProvider('invalidMetadata')]
    public function test_sensitive_values_cannot_be_smuggled_through_metadata_fields(string $field, mixed $value): void
    {
        $context = (new SecurityEvent('security.example', 'read', 'success'))->toArray();
        $context[$field] = $value;

        $filtered = SecurityEventPrivacy::filter($context);

        $this->assertArrayHasKey($field, $filtered);
        $this->assertNull($filtered[$field]);
        $this->assertCount(10, $filtered);
    }

    public function test_route_templates_are_allowed_but_query_strings_are_excluded(): void
    {
        $context = (new SecurityEvent('security.example', 'read', 'success', resourceType: 'route', resourceId: 'api/admin/users/{user}'))->toArray();
        $this->assertSame('api/admin/users/{user}', SecurityEventPrivacy::filter($context)['resource_id']);

        $context['resource_id'] = 'api/admin/users?token=private-token';
        $this->assertNull(SecurityEventPrivacy::filter($context)['resource_id']);
    }

    public static function invalidMetadata(): array
    {
        return [
            'nested actor' => ['actor_id', ['email' => 'private@example.com']],
            'nested resource' => ['resource_id', ['medical_history' => 'private-diagnosis']],
            'resource email' => ['resource_id', 'private@example.com'],
            'event text' => ['event', 'Diagnostico privado del paciente'],
            'action text' => ['action', 'Bearer private-token'],
            'resource type text' => ['resource_type', 'Datos privados del paciente'],
            'correlation token' => ['correlation_id', 'private-session-token'],
            'IP field email' => ['ip_address', 'private@example.com'],
        ];
    }
}
