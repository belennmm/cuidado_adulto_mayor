<?php

namespace App\Services;

use App\Support\BoundedResponseStream;
use App\Support\OutboundRequestException;
use GuzzleHttp\Handler\CurlHandler;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class SecureExternalHttpClient
{
    public function __construct(private readonly OutboundDestinationPolicy $policy) {}

    public function passwordRange(string $prefix): Response
    {
        try {
            if (! preg_match('/\A[0-9A-F]{5}\z/', $prefix) || ! extension_loaded('curl')) {
                throw new OutboundRequestException;
            }
            $destination = $this->policy->resolve('https://api.pwnedpasswords.com/range/'.$prefix, 'https', config('outbound.https_hosts', []));
            $maximum = 1000000;
            $address = $destination['addresses'][0];
            $pinned = str_contains($address, ':') ? '['.$address.']' : $address;
            $protocols = defined('CURLOPT_PROTOCOLS_STR') ? [
                constant('CURLOPT_PROTOCOLS_STR') => 'HTTPS', constant('CURLOPT_REDIR_PROTOCOLS_STR') => 'HTTPS',
            ] : [CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS];
            $response = Http::withHeaders(['Add-Padding' => 'true', 'Accept-Encoding' => 'identity'])
                ->setHandler(new CurlHandler)
                ->connectTimeout(2)->timeout(4)->withOptions([
                    'verify' => true, 'allow_redirects' => false, 'proxy' => '', 'cookies' => false,
                    'decode_content' => false, 'idn_conversion' => false,
                    'sink' => new BoundedResponseStream($maximum),
                    'on_headers' => function (ResponseInterface $response) use ($maximum): void {
                        $length = $response->getHeaderLine('Content-Length');
                        if ($length !== '' && (! ctype_digit($length) || (float) $length > $maximum)) {
                            throw new OutboundRequestException;
                        }
                    },
                    'curl' => [
                        CURLOPT_RESOLVE => [$destination['host'].':'.$destination['port'].':'.$pinned],
                        CURLOPT_FRESH_CONNECT => true, CURLOPT_FORBID_REUSE => true,
                    ] + $protocols,
                ])->get($destination['url']);
            if ($response->redirect() || strlen($response->body()) > $maximum) {
                throw new OutboundRequestException;
            }

            return $response;
        } catch (Throwable) {
            app(SecurityInfrastructureAudit::class)->record('outbound_request', 'failure');
            throw new OutboundRequestException;
        }
    }
}
