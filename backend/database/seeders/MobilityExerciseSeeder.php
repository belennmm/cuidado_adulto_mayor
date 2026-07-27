<?php

namespace Database\Seeders;

use App\Models\MobilityExercise;
use Illuminate\Database\Seeder;

class MobilityExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $exercises = [
            [
                'slug' => 'cuello-suave',
                'title' => 'Movilidad suave de cuello',
                'focus' => 'Cuello y hombros',
                'duration_minutes' => 2,
                'repetitions' => '5 repeticiones por lado',
                'instructions' => [
                    'Siéntate con la espalda apoyada y los pies firmes en el suelo.',
                    'Gira lentamente la cabeza hacia un lado sin forzar el movimiento.',
                    'Vuelve al centro y repite hacia el otro lado.',
                ],
                'precaution' => 'Suspende el ejercicio si aparece dolor, mareo o molestia.',
                'sort_order' => 10,
            ],
            [
                'slug' => 'hombros-circulos',
                'title' => 'Círculos de hombros',
                'focus' => 'Hombros y parte superior de la espalda',
                'duration_minutes' => 3,
                'repetitions' => '8 círculos hacia cada dirección',
                'instructions' => [
                    'Mantén una postura cómoda, sentado o de pie con apoyo cercano.',
                    'Eleva los hombros y llévalos suavemente hacia atrás y abajo.',
                    'Cambia de dirección después de completar las repeticiones.',
                ],
                'precaution' => 'Realiza movimientos lentos y sin elevar los hombros con tensión.',
                'sort_order' => 20,
            ],
            [
                'slug' => 'tobillos-flexion',
                'title' => 'Flexión de tobillos',
                'focus' => 'Tobillos y piernas',
                'duration_minutes' => 3,
                'repetitions' => '10 repeticiones por pie',
                'instructions' => [
                    'Siéntate en una silla estable con los pies apoyados.',
                    'Eleva la punta de un pie manteniendo el talón en el suelo.',
                    'Luego eleva el talón y repite alternando ambos pies.',
                ],
                'precaution' => 'Usa calzado antideslizante y mantén la silla sobre una superficie firme.',
                'sort_order' => 30,
            ],
            [
                'slug' => 'marcha-sentada',
                'title' => 'Marcha sentada',
                'focus' => 'Caderas y piernas',
                'duration_minutes' => 3,
                'repetitions' => '10 elevaciones por pierna',
                'instructions' => [
                    'Siéntate hacia el fondo de una silla estable.',
                    'Eleva una rodilla de forma controlada y bájala lentamente.',
                    'Alterna las piernas manteniendo el abdomen relajado.',
                ],
                'precaution' => 'Cuenta con supervisión si existe riesgo de pérdida de equilibrio.',
                'sort_order' => 40,
            ],
        ];

        foreach ($exercises as $exercise) {
            MobilityExercise::updateOrCreate(
                ['slug' => $exercise['slug']],
                [...$exercise, 'is_active' => true],
            );
        }
    }
}
