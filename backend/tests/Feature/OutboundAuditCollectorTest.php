<?php

namespace Tests\Feature;

use App\Logging\AuditCollectorHandler;
use App\Services\OutboundDnsResolver;
use App\Support\OutboundRequestException;
use Tests\TestCase;

class OutboundAuditCollectorTest extends TestCase
{
    private function resolver(string $address): void
    {
        $this->app->instance(OutboundDnsResolver::class, new class($address) extends OutboundDnsResolver
        {
            public function __construct(private readonly string $address) {}

            public function resolve(string $host, array $visited = []): array
            {
                return [$this->address];
            }
        });
    }

    private function collector(): AuditCollectorHandler
    {
        return new class('tls://audit.example.com:6514') extends AuditCollectorHandler
        {
            public ?string $endpoint = null;

            public array $tls = [];

            public function connectForTest()
            {
                return $this->fsockopen();
            }

            protected function openPinnedConnection(string $endpoint, $context)
            {
                $this->endpoint = $endpoint;
                $this->tls = stream_context_get_options($context)['ssl'];

                return false;
            }
        };
    }

    public function test_collector_connects_to_validated_ip_and_verifies_original_tls_hostname(): void
    {
        config(['outbound.tls_hosts' => ['audit.example.com' => [6514]]]);
        $this->resolver('2606:4700:4700::1111');
        $collector = $this->collector();
        $collector->connectForTest();
        $this->assertSame('tls://[2606:4700:4700::1111]:6514', $collector->endpoint);
        $this->assertSame('audit.example.com', $collector->tls['peer_name']);
        $this->assertTrue($collector->tls['verify_peer']);
        $this->assertTrue($collector->tls['verify_peer_name']);
        $this->assertFalse($collector->tls['allow_self_signed']);
    }

    public function test_private_collector_never_opens_a_socket(): void
    {
        config(['outbound.tls_hosts' => ['audit.example.com' => [6514]]]);
        $this->resolver('10.0.0.1');
        $collector = $this->collector();
        try {
            $collector->connectForTest();
            $this->fail('Private collector accepted.');
        } catch (OutboundRequestException) {
            $this->assertNull($collector->endpoint);
        }
    }

    public function test_unapproved_collector_never_opens_a_socket(): void
    {
        config(['outbound.tls_hosts' => []]);
        $this->resolver('93.184.216.34');
        $collector = $this->collector();
        try {
            $collector->connectForTest();
            $this->fail('Unapproved collector accepted.');
        } catch (OutboundRequestException) {
            $this->assertNull($collector->endpoint);
        }
    }
}
