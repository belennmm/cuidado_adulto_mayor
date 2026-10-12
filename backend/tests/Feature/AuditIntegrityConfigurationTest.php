<?php

namespace Tests\Feature;

use App\Support\AuditRecordIntegrity;
use InvalidArgumentException;
use Tests\TestCase;

class AuditIntegrityConfigurationTest extends TestCase
{
    public function test_production_requires_a_dedicated_integrity_key(): void
    {
        $this->app->instance('env', 'production');
        config(['security_audit.integrity_key' => null]);
        $this->app->forgetInstance(AuditRecordIntegrity::class);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SECURITY_AUDIT_INTEGRITY_KEY');

        $this->app->make(AuditRecordIntegrity::class);
    }

    public function test_production_accepts_a_dedicated_key(): void
    {
        $this->app->instance('env', 'production');
        config(['security_audit.integrity_key' => 'base64:'.base64_encode(str_repeat('b', 32))]);
        $this->app->forgetInstance(AuditRecordIntegrity::class);

        $this->assertInstanceOf(AuditRecordIntegrity::class, $this->app->make(AuditRecordIntegrity::class));
    }
}
