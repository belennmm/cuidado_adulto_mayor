<?php

namespace App\Logging;

use App\Support\AuditRecordIntegrity;
use Illuminate\Log\Logger;

class ProtectSecurityAuditLog
{
    public function __construct(private readonly AuditRecordIntegrity $integrity) {}

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        $monolog->pushProcessor($this->integrity);
        $monolog->pushProcessor(new SanitizeSecurityAuditLog());
        $collector = config('security_audit.collector_url');
        if (is_string($collector) && $collector !== '') {
            $monolog->setHandlers([...$monolog->getHandlers(), new AuditCollectorHandler($collector)]);
        }
    }
}
