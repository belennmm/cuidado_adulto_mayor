<?php

namespace Tests\Feature;

use App\Services\OutboundDnsResolver;
use App\Services\SecureExternalHttpClient;
use App\Support\OutboundRequestException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecureExternalHttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->resolver(['93.184.216.34']);
    }

    private function resolver(array $addresses): void
    {
        $this->app->instance(OutboundDnsResolver::class, new class($addresses) extends OutboundDnsResolver
        {
            public function __construct(private readonly array $addresses) {}

            public function resolve(string $host, array $visited = []): array
            {
                return $this->addresses;
            }
        });
    }

    public function test_transport_pins_dns_disables_proxies_redirects_and_tls_bypasses(): void
    {
        Http::fake(function ($request, $options) {
            $this->assertSame('https://api.pwnedpasswords.com/range/ABCDE', $request->url());
            $this->assertSame(['api.pwnedpasswords.com:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);
            if (defined('CURLOPT_PROTOCOLS_STR')) {
                $this->assertSame('HTTPS', $options['curl'][constant('CURLOPT_PROTOCOLS_STR')]);
            } else {
                $this->assertSame(CURLPROTO_HTTPS, $options['curl'][CURLOPT_PROTOCOLS]);
            }
            $this->assertTrue($options['curl'][CURLOPT_FRESH_CONNECT]);
            $this->assertTrue($options['curl'][CURLOPT_FORBID_REUSE]);
            $this->assertFalse($options['allow_redirects']);
            $this->assertSame('', $options['proxy']);
            $this->assertTrue($options['verify']);
            $this->assertSame(2, $options['connect_timeout']);
            $this->assertSame(4, $options['timeout']);
            $this->assertFalse($options['decode_content']);
            $this->assertFalse($request->hasHeader('Authorization'));
            $this->assertFalse($request->hasHeader('Cookie'));

            return Http::response('safe-response');
        });
        $this->assertSame('safe-response', app(SecureExternalHttpClient::class)->passwordRange('ABCDE')->body());
        Http::assertSentCount(1);
    }

    public function test_private_dns_address_prevents_any_http_request(): void
    {
        $this->resolver(['93.184.216.34', '169.254.169.254']);
        Http::fake();
        try {
            app(SecureExternalHttpClient::class)->passwordRange('ABCDE');
            $this->fail('Private address accepted.');
        } catch (OutboundRequestException $exception) {
            $this->assertStringNotContainsString('169.254', $exception->getMessage());
            Http::assertNothingSent();
        }
    }

    public function test_rebinding_is_pinned_and_a_later_request_revalidates_dns(): void
    {
        $resolver = new class extends OutboundDnsResolver
        {
            public int $calls = 0;

            public function resolve(string $host, array $visited = []): array
            {
                return ++$this->calls === 1 ? ['93.184.216.34'] : ['127.0.0.1'];
            }
        };
        $this->app->instance(OutboundDnsResolver::class, $resolver);
        Http::fake(fn ($request, $options) => Http::response($options['curl'][CURLOPT_RESOLVE][0]));
        $client = app(SecureExternalHttpClient::class);
        $this->assertSame('api.pwnedpasswords.com:443:93.184.216.34', $client->passwordRange('ABCDE')->body());
        try {
            $client->passwordRange('ABCDE');
            $this->fail('Rebound address accepted.');
        } catch (OutboundRequestException) {
            Http::assertSentCount(1);
            $this->assertSame(2, $resolver->calls);
        }
    }

    #[DataProvider('redirectStatuses')]
    public function test_redirects_never_follow_internal_targets(int $status): void
    {
        Http::fake(['*' => Http::response('', $status, ['Location' => 'http://169.254.169.254/latest/meta-data/'])]);
        try {
            app(SecureExternalHttpClient::class)->passwordRange('ABCDE');
            $this->fail('Redirect accepted.');
        } catch (OutboundRequestException) {
            Http::assertSentCount(1);
        }
    }

    public static function redirectStatuses(): array
    {
        return [[301], [302], [303], [307], [308]];
    }

    public function test_ipv6_pinning_uses_brackets_without_changing_the_tls_hostname(): void
    {
        $this->resolver(['2606:4700:4700::1111']);
        Http::fake(function ($request, $options) {
            $this->assertSame(['api.pwnedpasswords.com:443:[2606:4700:4700::1111]'], $options['curl'][CURLOPT_RESOLVE]);
            $this->assertStringStartsWith('https://api.pwnedpasswords.com/', $request->url());

            return Http::response('safe');
        });
        app(SecureExternalHttpClient::class)->passwordRange('ABCDE');
    }

    public function test_arbitrary_user_urls_cannot_be_used_as_a_password_prefix(): void
    {
        Http::fake();
        $this->expectException(OutboundRequestException::class);
        try {
            app(SecureExternalHttpClient::class)->passwordRange('https://127.0.0.1/');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_oversized_body_is_rejected_even_without_content_length(): void
    {
        Http::fake(['*' => Http::response(str_repeat('x', 1000001))]);
        $this->expectException(OutboundRequestException::class);
        app(SecureExternalHttpClient::class)->passwordRange('ABCDE');
    }
}
