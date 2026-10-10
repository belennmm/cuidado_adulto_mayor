<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SecurityScanSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \LogicException('Las cuentas de escaneo no se pueden crear en produccion.');
        }
        $accounts = [
            [
                'name' => 'ZAP Admin',
                'email' => 'zap.admin@example.test',
                'password' => 'ZapAdmin-2026!',
                'role' => 'admin',
            ],
            [
                'name' => 'ZAP Professional',
                'email' => 'zap.professional@example.test',
                'password' => 'ZapPro-2026!',
                'role' => 'profesional',
            ],
            [
                'name' => 'ZAP Family',
                'email' => 'zap.family@example.test',
                'password' => 'ZapFamily-2026!',
                'role' => 'familiar',
            ],
        ];

        foreach ($accounts as $account) {
            User::firstOrNew(['email' => $account['email']])->forceFill(
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'is_approved' => true,
                    'privacy_consent_at' => null,
                    'privacy_policy_version' => null,
                ]
            )->save();
        }
    }
}
