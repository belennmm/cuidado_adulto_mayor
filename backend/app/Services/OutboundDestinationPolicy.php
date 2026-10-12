<?php

namespace App\Services;

use App\Support\OutboundRequestException;
use Symfony\Component\HttpFoundation\IpUtils;

class OutboundDestinationPolicy
{
    public function __construct(private readonly OutboundDnsResolver $dns) {}

    public function resolve(string $url, string $scheme, array $allowedHosts): array
    {
        if (strlen($url) > 2048 || preg_match('/[^\x21-\x7E]|[\\\\]/', $url)
            || ! in_array($scheme, ['https', 'tls'], true)) {
            throw new OutboundRequestException;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== $scheme
            || array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($parts)) !== []) {
            throw new OutboundRequestException;
        }
        $host = strtolower($parts['host'] ?? '');
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 0);
        if (strlen($host) > 253 || ! preg_match('/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}\z/', $host)
            || ! in_array($port, $allowedHosts[$host] ?? [], true)
            || ($scheme === 'tls' && isset($parts['path']))) {
            throw new OutboundRequestException;
        }
        $path = $parts['path'] ?? '/';
        if (! preg_match('/\A\/[a-zA-Z0-9_\/-]*\z/', $path) || str_contains($path, '//')) {
            throw new OutboundRequestException;
        }
        $addresses = $this->dns->resolve($host);
        if ($addresses === [] || count($addresses) > 64) {
            throw new OutboundRequestException;
        }
        foreach ($addresses as $address) {
            if (! $this->isPublicIp($address)) {
                throw new OutboundRequestException;
            }
        }

        return ['url' => $scheme.'://'.$host.($port === 443 && $scheme === 'https' ? '' : ':'.$port).($scheme === 'https' ? $path : ''),
            'host' => $host, 'port' => $port, 'addresses' => $addresses];
    }

    public function isPublicIp(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        if (str_contains($address, ':')) {
            return IpUtils::checkIp($address, '2000::/3')
                && ! IpUtils::checkIp($address, ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20']);
        }

        return ! IpUtils::checkIp($address, [
            '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
            '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16',
            '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
            '168.63.129.16/32',
        ]);
    }
}
