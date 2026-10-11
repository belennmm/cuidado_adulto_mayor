<?php

namespace Database\Seeders;

use App\Support\SeedCredentials;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        SeedCredentials::guard();
        $this->call([
            InitialDataSeeder::class,
            MobilityExerciseSeeder::class,
        ]);
    }
}
