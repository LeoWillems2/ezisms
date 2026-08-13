<?php

namespace Database\Factories;

use App\Models\Leverancier;
use App\Models\Leveranciersbeoordeling;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Leveranciersbeoordeling>
 */
class LeveranciersbeoordelingFactory extends Factory
{
    protected $model = Leveranciersbeoordeling::class;

    public function definition(): array
    {
        return [
            'leverancier_id' => Leverancier::factory(),
            'uitgevoerd_op' => now()->toDateString(),
            'bevindingen' => fake()->sentence(),
            'volgende_beoordeling_gepland' => now()->addYear()->toDateString(),
            'uitgevoerd_door_id' => null,
        ];
    }
}
