<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\SeedCredentials;
use Illuminate\Database\Seeder;

class SecurityScanSeeder extends Seeder
{
    public function run(): void
    {
        SeedCredentials::guard();
        $accounts = [
            [
                'name' => 'ZAP Admin',
                'email' => 'zap.admin@example.test',
                'password' => SeedCredentials::password('scan_admin'),
                'role' => 'admin',
            ],
            [
                'name' => 'ZAP Professional',
                'email' => 'zap.professional@example.test',
                'password' => SeedCredentials::password('scan_professional'),
                'role' => 'profesional',
            ],
            [
                'name' => 'ZAP Family',
                'email' => 'zap.family@example.test',
                'password' => SeedCredentials::password('scan_family'),
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
