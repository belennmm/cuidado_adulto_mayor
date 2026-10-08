<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::FAMILY->value,
            'is_approved' => false,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::ADMIN->value,
            'is_approved' => true,
        ]);
    }

    public function approvedFamily(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::FAMILY->value,
            'is_approved' => true,
        ]);
    }

    public function approvedProfessional(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::PROFESSIONAL->value,
            'is_approved' => true,
        ]);
    }

    public function pendingProfessional(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::PROFESSIONAL->value,
            'is_approved' => false,
        ]);
    }
}
