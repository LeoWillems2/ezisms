<?php

namespace Database\Factories;

use App\Models\OrganisatieEenheid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<OrganisatieEenheid>
 */
class OrganisatieEenheidFactory extends Factory
{
    protected $model = OrganisatieEenheid::class;

    public function definition(): array
    {
        return [
            'naam' => fake()->company(),
            'type' => fake()->randomElement(['afdeling', 'locatie', 'proces']),
            'bovenliggende_eenheid_id' => null,
        ];
    }

    /** Een eenheid van type 'afdeling' — de enige die als doelgroep kan dienen. */
    public function afdeling(): static
    {
        return $this->state(fn () => ['type' => OrganisatieEenheid::TYPE_AFDELING]);
    }
}
