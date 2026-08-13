<?php

namespace Database\Factories;

use App\Models\Doelgroep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Doelgroep>
 */
class DoelgroepFactory extends Factory
{
    protected $model = Doelgroep::class;

    public function definition(): array
    {
        return [
            'naam' => fake()->unique()->jobTitle(),
            'omschrijving' => null,
        ];
    }
}
