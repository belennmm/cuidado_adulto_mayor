<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

final class RedactSecrets
{
    /**
     * Credentials, request bodies and clinical fields must never reach a log
     * handler. Keep the decision here so every configured channel applies the
     * same policy, including channels added for production deployments.
     */
    public static function sensitive(string $key): bool
    {
        return (bool) preg_match(
            '/password|token|secret|authorization|cookie|challenge|(?:^|_)code(?:s|$)|bindings|payload|'.
            'diagnos|clinical|medical_history|allerg|medication|medicine|dosage|prescription|'.
            '(?:^|_)(?:notes?|observations?|symptoms?|conditions?|treatments?)(?:_|$)/i',
            $key,
        );
    }

    public static function text(string $text): string
    {
        $text = preg_replace('/Bearer\s+[^\s,;]+/i', 'Bearer [REDACTED]', $text);
        // Discard the rest of a credential-bearing line, including quoted passwords with spaces.
        $text = preg_replace('/((?:password|token|secret|authorization|cookie|challenge|code)[\w-]*["\x27]?\s*[:=]\s*)[^\r\n]*/i', '$1[REDACTED]', $text);

        // Standalone reset/challenge/backup tokens must not survive exception messages.
        return preg_replace('/\b[a-f0-9]{32,}\b/i', '[REDACTED]', $text);
    }

    public static function values(array $values): array
    {
        foreach ($values as $key => $value) {
            if (self::sensitive((string) $key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = self::values($value);
            } elseif (is_object($value)) {
                $values[$key] = '[object '.$value::class.']';
            } elseif (is_string($value)) {
                $values[$key] = self::text($value);
            }
        }

        return $values;
    }

    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(fn (LogRecord $record) => $record->with(message: self::text($record->message), context: self::values($record->context), extra: self::values($record->extra)));
    }
}
