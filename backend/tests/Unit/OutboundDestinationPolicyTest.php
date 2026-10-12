<?php

namespace Tests\Unit;

use App\Services\OutboundDestinationPolicy;
use App\Services\OutboundDnsResolver;
use App\Support\BoundedResponseStream;
use App\Support\OutboundRequestException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OutboundDestinationPolicyTest extends TestCase
{
    private function policy(array $addresses = ['93.184.216.34']): OutboundDestinationPolicy
    {
        return new OutboundDestinationPolicy(new class($addresses) extends OutboundDnsResolver
        {
            public function __construct(private readonly array $addresses) {}

            public function resolve(string $host, array $visited = []): array
            {
                return $this->addresses;
            }
        });
    }

    #[DataProvider('blockedAddresses')]
    public function test_non_public_addresses_are_blocked(string $address): void
    {
        $this->assertFalse($this->policy()->isPublicIp($address));
        $this->expectException(OutboundRequestException::class);
        $this->policy([$address])->resolve('https://api.pwnedpasswords.com/range/ABCDE', 'https', ['api.pwnedpasswords.com' => [443]]);
    }

    public static function blockedAddresses(): array
    {
        return array_map(fn ($address) => [$address], [
            '127.0.0.1', '127.1', '2130706433', '0177.0.0.1', '0x7f000001', '0.0.0.0', '0.1.2.3',
            '10.1.2.3', '172.16.1.1', '172.31.255.255', '192.168.1.1', '169.254.169.254', '100.100.100.200',
            '100.64.0.1', '192.0.0.1', '192.0.2.1', '198.18.0.1', '198.51.100.1', '203.0.113.1',
            '168.63.129.16', '224.0.0.1', '255.255.255.255', '::', '::1', '::ffff:127.0.0.1', '::ffff:8.8.8.8',
            'fc00::1', 'fd00::1', 'fe80::1', 'ff02::1', '64:ff9b::a00:1', '64:ff9b:1::1',
            '2001:db8::1', '2001::1', '2002:7f00:1::', '3fff::1', 'not-an-ip',
        ]);
    }

    #[DataProvider('unsafeUrls')]
    public function test_ambiguous_urls_schemes_ports_and_hosts_are_rejected(string $url): void
    {
        $this->expectException(OutboundRequestException::class);
        $this->policy()->resolve($url, 'https', ['api.pwnedpasswords.com' => [443]]);
    }

    public static function unsafeUrls(): array
    {
        return array_map(fn ($url) => [$url], [
            'http://api.pwnedpasswords.com/range/ABCDE', 'file:///etc/passwd', 'ftp://api.pwnedpasswords.com/file',
            'gopher://127.0.0.1/', '//api.pwnedpasswords.com/range/ABCDE', 'https://127.0.0.1/', 'https://[::1]/',
            'https://2130706433/', 'https://0177.0.0.1/', 'https://0x7f000001/',
            'https://api.pwnedpasswords.com.evil.test/', 'https://evil.test/', 'https://api.pwnedpasswords.com:80/',
            'https://api.pwnedpasswords.com:444/', 'https://user:secret@api.pwnedpasswords.com/',
            'https://api.pwnedpasswords.com@127.0.0.1/', 'https://api.pwnedpasswords.com./',
            'https://%61pi.pwnedpasswords.com/', 'https://api.pwnedpasswords.com/range/%2e%2e/',
            'https://api.pwnedpasswords.com/../', 'https://api.pwnedpasswords.com//evil.test',
            'https://api.pwnedpasswords.com/?url=http://127.0.0.1', 'https://api.pwnedpasswords.com/#secret',
            "https://api.pwnedpasswords.com/\r\nsecret", 'https://api.pwnedpasswords.com\\@127.0.0.1/',
            'https://api.pwnedpasswords.com:99999/',
        ]);
    }

    public function test_every_dns_address_is_validated_not_only_the_first(): void
    {
        $this->expectException(OutboundRequestException::class);
        $this->policy(['93.184.216.34', '::1'])->resolve('https://api.pwnedpasswords.com/', 'https', ['api.pwnedpasswords.com' => [443]]);
    }

    public function test_public_ipv4_ipv6_and_uppercase_host_normalize_to_an_exact_destination(): void
    {
        $policy = $this->policy(['93.184.216.34', '2606:4700:4700::1111']);
        $destination = $policy->resolve('HTTPS://API.PWNEDPASSWORDS.COM:443/range/ABCDE', 'https', ['api.pwnedpasswords.com' => [443]]);
        $this->assertSame('https://api.pwnedpasswords.com/range/ABCDE', $destination['url']);
        $this->assertCount(2, $destination['addresses']);
    }

    public function test_tls_collector_requires_its_own_exact_host_and_port_allowlist(): void
    {
        $this->assertSame(6514, $this->policy()->resolve('tls://audit.example.test:6514', 'tls', ['audit.example.test' => [6514]])['port']);
        $this->expectException(OutboundRequestException::class);
        $this->policy()->resolve('tls://audit.example.test:6514', 'tls', []);
    }

    public function test_empty_dns_answers_fail_closed(): void
    {
        $this->expectException(OutboundRequestException::class);
        $this->policy([])->resolve('https://api.pwnedpasswords.com/', 'https', ['api.pwnedpasswords.com' => [443]]);
    }

    public function test_response_stream_stops_before_exceeding_its_budget(): void
    {
        $stream = new BoundedResponseStream(8);
        $this->assertSame(4, $stream->write('1234'));
        $this->assertSame(4, $stream->write('5678'));
        try {
            $stream->write('9');
            $this->fail('Oversized response accepted.');
        } catch (OutboundRequestException) {
            $this->assertSame(8, $stream->getSize());
            $this->assertSame('12345678', (string) $stream);
        } finally {
            $stream->close();
        }
    }
}
