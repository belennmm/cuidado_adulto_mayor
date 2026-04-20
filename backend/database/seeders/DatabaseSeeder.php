<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            [
                'email' => 'admin@example.com',
                'name' => 'Admin User',
                'password' => 'admin123',
                'role' => 'admin',
                'is_approved' => true,
                'location' => 'Guatemala',
                'phone' => '5555-0001',
                'birthdate' => '1990-01-01',
            ],
            [
                'email' => 'familiar@example.com',
                'name' => 'Cuidador Familiar',
                'password' => 'password',
                'role' => 'familiar',
                'is_approved' => true,
                'location' => 'Guatemala',
                'phone' => '5555-0002',
                'birthdate' => '1988-05-10',
            ],
            [
                'email' => 'profesional@example.com',
                'name' => 'Cuidador Profesional',
                'password' => 'password',
                'role' => 'profesional',
                'is_approved' => true,
                'location' => 'Guatemala',
                'phone' => '5555-0003',
                'birthdate' => '1985-09-15',
            ],
            [
                'email' => 'pendiente@example.com',
                'name' => 'Usuario Pendiente',
                'password' => 'password',
                'role' => 'familiar',
                'is_approved' => false,
                'location' => 'Guatemala',
                'phone' => '5555-0004',
                'birthdate' => '1995-03-20',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make($user['password']),
                    'role' => $user['role'],
                    'is_approved' => $user['is_approved'],
                    'location' => $user['location'],
                    'phone' => $user['phone'],
                    'birthdate' => $user['birthdate'],
                ],
            );
        }
    }
}
