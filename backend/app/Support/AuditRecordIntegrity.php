<?php

namespace App\Support;

use InvalidArgumentException;
use Monolog\LogRecord;

final class AuditRecordIntegrity
{
    private readonly string $key;

    public function __construct(string $key, private readonly string $keyId = 'v1')
    {
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        if (! is_string($decoded) || strlen($decoded) < 32) {
            throw new InvalidArgumentException('SECURITY_AUDIT_INTEGRITY_KEY requiere al menos 32 bytes.');
        }
        if (! preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $keyId)) {
            throw new InvalidArgumentException('SECURITY_AUDIT_KEY_ID no es valido.');
        }
        $this->key = $decoded;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $payload = [
            'message' => $record->message, 'context' => $record->context,
            'channel' => $record->channel, 'level_name' => $record->level->getName(),
        ];
        $record->extra['audit_integrity'] = [
            'version' => 1, 'algorithm' => 'hmac-sha256', 'key_id' => $this->keyId,
            'signature' => $this->signature($payload),
        ];

        return $record;
    }

    public function verify(array $record): bool
    {
        $integrity = $record['extra']['audit_integrity'] ?? null;
        if (! is_array($integrity) || ($integrity['version'] ?? null) !== 1
            || ($integrity['algorithm'] ?? null) !== 'hmac-sha256'
            || ($integrity['key_id'] ?? null) !== $this->keyId
            || ! is_string($integrity['signature'] ?? null)) {
            return false;
        }
        $payload = [];
        foreach (['message', 'context', 'channel', 'level_name'] as $field) {
            if (! array_key_exists($field, $record)) {
                return false;
            }
            $payload[$field] = $record[$field];
        }

        return hash_equals($this->signature($payload), $integrity['signature']);
    }

    private function signature(array $payload): string
    {
        return hash_hmac('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR), $this->key);
    }

    private function canonicalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonicalize($item);
            }
        }

        return $value;
    }
}
