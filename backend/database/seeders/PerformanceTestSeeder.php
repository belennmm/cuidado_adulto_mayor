<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class PerformanceTestSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Las cuentas de rendimiento no pueden crearse en producción.');
        }

        $accounts = [
            [
                'name' => 'Performance Admin',
                'email' => 'admin@performance.test',
                'password' => 'Performance123!',
                'role' => 'admin',
            ],
            [
                'name' => 'Performance Professional',
                'email' => 'professional@performance.test',
                'password' => 'Performance123!',
                'role' => 'profesional',
            ],
            [
                'name' => 'Performance Family',
                'email' => 'family@performance.test',
                'password' => 'Performance123!',
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
