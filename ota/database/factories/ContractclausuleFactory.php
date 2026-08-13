<?php

namespace Database\Factories;

use App\Models\Contractclausule;
use App\Models\Leverancier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Contractclausule>
 */
class ContractclausuleFactory extends Factory
{
    protected $model = Contractclausule::class;

    public function definition(): array
    {
        return [
            'leverancier_id' => Leverancier::factory(),
            'type' => fake()->randomElement(array_keys(Contractclausule::TYPES)),
            'aanwezig' => true,
        ];
    }
}
