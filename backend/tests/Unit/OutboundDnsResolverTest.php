<?php

namespace Tests\Unit;

use App\Services\OutboundDestinationPolicy;
use App\Services\OutboundDnsResolver;
use App\Support\OutboundRequestException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OutboundDnsResolverTest extends TestCase
{
    private function resolver(array $records): OutboundDnsResolver
    {
        return new class($records) extends OutboundDnsResolver
        {
            public function __construct(private readonly array $records) {}

            protected function query(string $host): array|false
            {
                return $this->records[$host] ?? false;
            }
        };
    }

    public function test_cname_resolution_includes_all_a_and_aaaa_answers(): void
    {
        $resolver = $this->resolver([
            'safe.example.com' => [['type' => 'CNAME', 'target' => 'cdn.example.com.']],
            'cdn.example.com' => [['type' => 'A', 'ip' => '93.184.216.34'], ['type' => 'AAAA', 'ipv6' => '2606:4700:4700::1111']],
        ]);
        $this->assertSame(['93.184.216.34', '2606:4700:4700::1111'], $resolver->resolve('safe.example.com'));
    }

    public function test_cname_to_internal_address_cannot_bypass_the_destination_policy(): void
    {
        $resolver = $this->resolver([
            'safe.example.com' => [['type' => 'A', 'ip' => '93.184.216.34'], ['type' => 'CNAME', 'target' => 'internal.example.com']],
            'internal.example.com' => [['type' => 'A', 'ip' => '10.0.0.1']],
        ]);
        $this->expectException(OutboundRequestException::class);
        (new OutboundDestinationPolicy($resolver))->resolve('https://safe.example.com/', 'https', ['safe.example.com' => [443]]);
    }

    public function test_cname_cycles_fail_closed(): void
    {
        $resolver = $this->resolver([
            'safe.example.com' => [['type' => 'CNAME', 'target' => 'cdn.example.com']],
            'cdn.example.com' => [['type' => 'CNAME', 'target' => 'safe.example.com']],
        ]);
        $this->expectException(RuntimeException::class);
        $resolver->resolve('safe.example.com');
    }

    public function test_dns_failure_does_not_fall_back_to_an_unvalidated_address(): void
    {
        $this->expectException(RuntimeException::class);
        $this->resolver([])->resolve('safe.example.com');
    }
}
