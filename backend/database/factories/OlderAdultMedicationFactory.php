<?php

namespace Database\Factories;

use App\Models\Medication;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OlderAdultMedication>
 */
class OlderAdultMedicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'older_adult_id' => OlderAdult::factory(),
            'medication_id' => Medication::factory(),
            'dosage' => '1 tableta',
            'is_active' => true,
        ];
    }
}
