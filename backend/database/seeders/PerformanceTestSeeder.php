<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\SeedCredentials;
use Illuminate\Database\Seeder;

class PerformanceTestSeeder extends Seeder
{
    public function run(): void
    {
        SeedCredentials::guard();

        $accounts = [
            [
                'name' => 'Performance Admin',
                'email' => 'admin@performance.test',
                'password' => SeedCredentials::password('performance'),
                'role' => 'admin',
            ],
            [
                'name' => 'Performance Professional',
                'email' => 'professional@performance.test',
                'password' => SeedCredentials::password('performance'),
                'role' => 'profesional',
            ],
            [
                'name' => 'Performance Family',
                'email' => 'family@performance.test',
                'password' => SeedCredentials::password('performance'),
                'role' => 'familiar',
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'is_approved' => true,
                    'location' => 'Ambiente local de pruebas',
                ],
            );

            $user->tokens()->delete();
        }
    }
}
