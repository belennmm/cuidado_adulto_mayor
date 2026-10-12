<?php

namespace App\Logging;

use App\Support\SecurityEventPrivacy;
use Monolog\LogRecord;

final class SanitizeSecurityAuditLog
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: in_array($record->message, ['security.event', 'security.alert'], true) ? $record->message : 'security.event',
            context: SecurityEventPrivacy::filter($record->context),
            extra: [],
        );
    }
}
