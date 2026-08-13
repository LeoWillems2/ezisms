<?php

namespace Database\Factories;

use App\Models\Systeem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Systeem>
 */
class SysteemFactory extends Factory
{
    protected $model = Systeem::class;

    public function definition(): array
    {
        return [
            'naam' => fake()->words(2, true),
            'hostingtype' => fake()->randomElement(['intern', 'extern']),
            'leverancier_id' => null,
        ];
    }
}
