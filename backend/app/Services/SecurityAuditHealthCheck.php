<?php

namespace App\Services;

use App\Support\AuditRecordIntegrity;
use App\Support\SecurityEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SecurityAuditHealthCheck
{
    public function verify(): bool
    {
        $stream = null;
        try {
            $logger = Log::channel('security_audit');
            $path = $logger->getLogger()->getHandlers()[0]->getUrl();
            clearstatcache(true, $path);
            $offset = is_file($path) ? filesize($path) : 0;
            $correlationId = (string) Str::uuid();
            $logger->info('security.event', (new SecurityEvent(
                'security.audit.health', 'verify', 'success', resourceType: 'audit_log', correlationId: $correlationId,
            ))->toArray());
            $stream = @fopen($path, 'rb');
            if ($stream === false || fseek($stream, $offset) !== 0) {
                throw new \RuntimeException;
            }
            $maximum = 1048576;
            $read = 0;
            while (! feof($stream) && $read < $maximum) {
                $line = fgets($stream, min(65536, $maximum - $read + 1));
                if ($line === false) {
                    break;
                }
                $read += strlen($line);
                $record = json_decode($line, true);
                if (is_array($record) && ($record['context']['correlation_id'] ?? null) === $correlationId
                    && ($record['context']['event'] ?? null) === 'security.audit.health'
                    && app(AuditRecordIntegrity::class)->verify($record)) {
                    return true;
                }
            }
        } catch (Throwable) {
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        error_log('security_audit_health_failed');

        return false;
    }
}
