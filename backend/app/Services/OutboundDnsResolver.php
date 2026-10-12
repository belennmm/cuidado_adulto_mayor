<?php

namespace App\Services;

use RuntimeException;

class OutboundDnsResolver
{
    public function resolve(string $host, array $visited = []): array
    {
        if (count($visited) >= 8 || in_array($host, $visited, true)) {
            throw new RuntimeException('Outbound DNS unavailable.');
        }
        $records = $this->query($host);
        if (! is_array($records) || $records === []) {
            throw new RuntimeException('Outbound DNS unavailable.');
        }
        $addresses = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
            if (isset($record['target']) && ($record['type'] ?? '') === 'CNAME') {
                $target = strtolower(rtrim($record['target'], '.'));
                if (! preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/', $target)) {
                    throw new RuntimeException('Outbound DNS unavailable.');
                }
                array_push($addresses, ...$this->resolve($target, [...$visited, $host]));
            }
        }
        if ($addresses === [] || count($addresses) > 64) {
            throw new RuntimeException('Outbound DNS unavailable.');
        }

        return array_values(array_unique($addresses));
    }

    protected function query(string $host): array|false
    {
        return @dns_get_record($host, DNS_A | DNS_AAAA | DNS_CNAME);
    }
}
