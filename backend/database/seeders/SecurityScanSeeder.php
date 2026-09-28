<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SecurityScanSeeder extends Seeder
{
    public function run(): void
    {
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
                'password' => 'ZapProfessional-2026!',
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
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'is_approved' => true,
                    'privacy_consent_at' => null,
                    'privacy_policy_version' => null,
                ]
            );
        }
    }
}
