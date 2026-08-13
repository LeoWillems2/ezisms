<?php

namespace Database\Factories;

use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    public function definition(): array
    {
        return [
            'aard' => fake()->randomElement(['intern', 'extern']),
            'categorie' => fake()->randomElement(['juridisch', 'technologisch', 'markt', 'organisatorisch']),
            'omschrijving' => fake()->sentence(),
            'laatst_beoordeeld_op' => null,
        ];
    }
}
