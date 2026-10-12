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

        $count = max(1, min(200, (int) env('PERFORMANCE_USERS_PER_ROLE', 120)));
        $roles = [
            'administrator' => ['name' => 'Admin', 'role' => 'admin', 'password' => SeedCredentials::password('performance_admin')],
            'professional' => ['name' => 'Professional', 'role' => 'profesional', 'password' => SeedCredentials::password('performance_professional')],
            'family' => ['name' => 'Family', 'role' => 'familiar', 'password' => SeedCredentials::password('performance_family')],
        ];

        foreach ($roles as $slug => $settings) {
            for ($index = 1; $index <= $count; $index++) {
                $user = User::query()->updateOrCreate(
                    ['email' => sprintf('perf-%s-%03d@performance.test', $slug, $index)],
                    [
                        'name' => sprintf('Performance %s %03d', $settings['name'], $index),
                        'password' => $settings['password'],
                        'role' => $settings['role'],
                        'is_approved' => true,
                        'location' => 'Ambiente local de pruebas',
                    ],
                );

                $user->tokens()->delete();
            }
        }
    }
}
