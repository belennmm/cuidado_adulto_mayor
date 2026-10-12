<?php

namespace App\Logging;

use App\Support\AuditRecordIntegrity;
use App\Support\AuditLogLimits;
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
        $remote = is_string($collector) && $collector !== '' ? new AuditCollectorHandler($collector) : null;
        $monolog->setHandlers([new LimitSecurityAuditLog(
            $monolog->getHandlers()[0], $remote,
            config('logging.channels.security_audit.path'), AuditLogLimits::settings(),
        )]);
    }
}
