<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

final class SecurityEventPrivacy
{
    private const FIELDS = [
        'schema_version', 'event', 'action', 'result', 'actor_id', 'resource_type',
        'resource_id', 'occurred_at', 'ip_address', 'correlation_id',
    ];

    public static function filter(array $context): array
    {
        $filtered = [];
        foreach (self::FIELDS as $field) {
            $value = $context[$field] ?? null;
            if (is_string($value) && preg_match('/[\x00-\x1F\x7F-\x{009F}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $value) !== 0) {
                $value = null;
            }
            $filtered[$field] = is_scalar($value) ? $value : null;
        }

        $filtered['schema_version'] = 1;
        if (! is_int($filtered['actor_id'])) {
            $filtered['actor_id'] = null;
        }
        $timestamp = $filtered['occurred_at'];
        $date = is_string($timestamp) && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/', $timestamp)
            ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $timestamp, new DateTimeZone('UTC')) : false;
        if ($date === false || $date->format('Y-m-d\TH:i:s.u\Z') !== $timestamp) {
            $filtered['occurred_at'] = null;
        }

        foreach (['event', 'action', 'result', 'resource_type'] as $field) {
            $value = $filtered[$field];
            if ($value !== null && (! is_string($value) || ! preg_match('/\A[a-z][a-z0-9_.-]{0,127}\z/', $value))) {
                $filtered[$field] = null;
            }
        }

        $id = $filtered['resource_id'];
        $numeric = is_int($id) || (is_string($id) && ctype_digit($id));
        $route = $filtered['resource_type'] === 'route' && is_string($id)
            && strlen($id) <= 512 && preg_match('/\Aapi(?:\/[a-zA-Z0-9_{}-]+)+\z/', $id);
        if (! $numeric && ! $route) {
            $filtered['resource_id'] = null;
        }

        $ip = $filtered['ip_address'];
        if (! is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $filtered['ip_address'] = null;
        } else {
            $filtered['ip_address'] = self::maskIp($ip);
        }
        $correlation = $filtered['correlation_id'];
        if (! is_string($correlation) || ! preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/i', $correlation)) {
            $filtered['correlation_id'] = null;
        }

        return $filtered;
    }

    private static function maskIp(string $ip): string
    {
        $packed = inet_pton($ip);
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $packed = substr($packed, 12);
        }

        $prefixBytes = strlen($packed) === 4 ? 3 : 6;

        return inet_ntop(substr($packed, 0, $prefixBytes).str_repeat("\0", strlen($packed) - $prefixBytes));
    }
}
