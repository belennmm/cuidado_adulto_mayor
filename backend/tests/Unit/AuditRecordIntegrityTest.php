<?php

namespace Tests\Unit;

use App\Support\AuditRecordIntegrity;
use DateTimeImmutable;
use InvalidArgumentException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class AuditRecordIntegrityTest extends TestCase
{
    public function test_signed_json_verifies_and_detects_modified_context(): void
    {
        $integrity = new AuditRecordIntegrity(str_repeat('a', 32));
        $record = $integrity(new LogRecord(new DateTimeImmutable(), 'security_audit', Level::Info, 'security.event', [
            'actor_id' => 42, 'result' => 'denied', 'occurred_at' => '2026-10-11T20:00:00.000000Z',
        ]));
        $json = (new JsonFormatter())->format($record);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($integrity->verify($decoded));
        $decoded['context']['result'] = 'success';
        $this->assertFalse($integrity->verify($decoded));
        $this->assertStringNotContainsString(str_repeat('a', 32), $json);
    }

    public function test_a_different_key_or_unsigned_record_fails_verification(): void
    {
        $record = (new AuditRecordIntegrity(str_repeat('a', 32)))(
            new LogRecord(new DateTimeImmutable(), 'security_audit', Level::Info, 'security.event'),
        );
        $decoded = json_decode((new JsonFormatter())->format($record), true, 512, JSON_THROW_ON_ERROR);

        $this->assertFalse((new AuditRecordIntegrity(str_repeat('b', 32)))->verify($decoded));
        unset($decoded['extra']['audit_integrity']);
        $this->assertFalse((new AuditRecordIntegrity(str_repeat('a', 32)))->verify($decoded));
    }

    public function test_weak_keys_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuditRecordIntegrity('short');
    }

    public function test_base64_keys_and_reordered_fields_verify(): void
    {
        $integrity = new AuditRecordIntegrity('base64:'.base64_encode(str_repeat('a', 32)));
        $record = $integrity(new LogRecord(new DateTimeImmutable(), 'security_audit', Level::Info, 'security.event', [
            'actor_id' => 42, 'result' => 'success',
        ]));
        $decoded = json_decode((new JsonFormatter())->format($record), true, 512, JSON_THROW_ON_ERROR);
        $decoded['context'] = array_reverse($decoded['context'], true);

        $this->assertTrue($integrity->verify($decoded));
    }
}
