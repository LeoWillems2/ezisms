<?php

namespace Database\Factories;

use App\Models\Wijzigingssjabloon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — de meegeleverde sjablonen staan in
 * `WijzigingssjabloonSeeder`.
 *
 * @extends Factory<Wijzigingssjabloon>
 */
class WijzigingssjabloonFactory extends Factory
{
    protected $model = Wijzigingssjabloon::class;

    public function definition(): array
    {
        return [
            'naam' => 'Sjabloon '.fake()->unique()->words(3, true),
            'soort' => 'leveranciersrelease',
            'zwaarte' => 'standaard',
            'actief' => true,
        ];
    }
}
