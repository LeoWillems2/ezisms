<?php

namespace Database\Factories;

use App\Models\Leverancier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Leverancier>
 */
class LeverancierFactory extends Factory
{
    protected $model = Leverancier::class;

    public function definition(): array
    {
        return [
            'naam' => fake()->company(),
            'status' => 'kandidaat',
            'risiconiveau' => fake()->randomElement(['laag', 'midden', 'hoog']),
            'eigen_certificering_geldig_tot' => null,
        ];
    }

    public function actief(): static
    {
        return $this->state(['status' => 'actief']);
    }

    public function hoogRisico(): static
    {
        return $this->state(['risiconiveau' => 'hoog']);
    }
}
