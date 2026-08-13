<?php

namespace Database\Factories;

use App\Models\Dienst;
use App\Models\Leverancier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Dienst>
 */
class DienstFactory extends Factory
{
    protected $model = Dienst::class;

    public function definition(): array
    {
        return [
            'leverancier_id' => Leverancier::factory(),
            'omschrijving' => fake()->words(3, true),
        ];
    }
}
