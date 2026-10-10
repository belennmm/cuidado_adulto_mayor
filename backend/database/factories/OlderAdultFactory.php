<?php

namespace Database\Factories;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OlderAdult>
 */
class OlderAdultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'age' => fake()->numberBetween(65, 100),
            'room' => fake()->bothify('?-###'),
            'status' => 'Estable',
            'created_by' => User::factory()->admin(),
        ];
    }

    public function assignedToProfessional(User $professional): static
    {
        return $this->state(fn () => [
            'professional_caregiver_id' => $professional->id,
            'created_by' => $professional->id,
        ]);
    }

    public function assignedToFamily(User $family): static
    {
        return $this->state(fn () => [
            'family_caregiver_id' => $family->id,
            'caregiver_family' => $family->name,
        ]);
    }
}
