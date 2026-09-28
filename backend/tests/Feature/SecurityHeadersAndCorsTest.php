<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersAndCorsTest extends TestCase
{
    public function test_api_responses_include_security_headers(): void
    {
        $this->getJson('/api/ping')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_web_responses_include_security_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    public function test_allowed_origin_receives_cors_header(): void
    {
        $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson('/api/ping')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Vary', 'Origin');
    }

    public function test_untrusted_origin_does_not_receive_cors_permission(): void
    {
        $response = $this->withHeader('Origin', 'https://attacker.example')
            ->getJson('/api/ping')
            ->assertOk();

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function test_preflight_from_untrusted_origin_grants_no_cors_permission(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://attacker.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/ping')->assertNoContent();

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }
}
