<?php

namespace Database\Factories;

use App\Models\Taaksjabloon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — de echte sjablonen komen uit
 * TaaksjabloonSeeder.
 *
 * @extends Factory<Taaksjabloon>
 */
class TaaksjabloonFactory extends Factory
{
    protected $model = Taaksjabloon::class;

    public function definition(): array
    {
        return [
            'naam' => fake()->sentence(3),
            'herhaling' => 'jaarlijks',
            'bron_blok' => 'risico-soa',
            'aanmaken_dagen_vooraf' => 14,
            'actief' => true,
        ];
    }
}
