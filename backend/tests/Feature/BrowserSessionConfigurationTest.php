<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class BrowserSessionConfigurationTest extends TestCase
{
    #[DataProvider('unsafeSettings')]
    public function test_production_refuses_unsafe_session_settings(array $settings, string $message): void
    {
        config(array_merge([
            'app.debug' => false, 'app.url' => 'https://cuidado.test', 'cors.allowed_origins' => ['https://cuidado.test'],
            'session.driver' => 'database', 'session.secure' => true, 'session.http_only' => true,
            'session.same_site' => 'lax', 'session.idle_timeout' => 15, 'session.absolute_timeout' => 60,
        ], $settings));
        $this->app->instance('env', 'production');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($message);
            (new AppServiceProvider($this->app))->boot();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public static function unsafeSettings(): array
    {
        return [
            [['session.secure' => false], 'SESSION_SECURE_COOKIE'],
            [['session.http_only' => false], 'SESSION_HTTP_ONLY'],
            [['session.same_site' => null], 'SESSION_SAME_SITE'],
            [['session.idle_timeout' => 0], 'tiempos de sesion'],
            [['session.absolute_timeout' => -1], 'tiempos de sesion'],
            [['session.driver' => 'cookie'], 'SESSION_DRIVER'],
            [['session.driver' => 'array'], 'SESSION_DRIVER'],
        ];
    }
}
