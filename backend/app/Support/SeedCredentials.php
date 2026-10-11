<?php

namespace App\Support;

use Illuminate\Support\Str;

final class SeedCredentials
{
    public static function guard(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Los seeders de prueba requieren APP_ENV local o testing.');
        }
    }

    public static function password(string $key): string
    {
        self::guard();
        $environment = ['demo' => 'DEMO_SEED_PASSWORD', 'scan_admin' => 'SECURITY_SCAN_ADMIN_PASSWORD', 'scan_professional' => 'SECURITY_SCAN_PROFESSIONAL_PASSWORD', 'scan_family' => 'SECURITY_SCAN_FAMILY_PASSWORD', 'performance' => 'PERFORMANCE_SEED_PASSWORD'];
        $password = getenv($environment[$key]) ?: config('seeding.passwords.'.$key) ?: Str::password(32);
        PasswordPolicy::validate($password);

        return $password;
    }
}
