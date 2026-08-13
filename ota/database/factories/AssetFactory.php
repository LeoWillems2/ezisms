<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\OrganisatieEenheid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'naam' => fake()->words(2, true),
            'type' => fake()->randomElement(['informatie', 'systeem_of_dienst', 'hardware']),
            'omschrijving' => fake()->sentence(),
            'organisatie_eenheid_id' => OrganisatieEenheid::factory(),
            'status' => 'geregistreerd',
            'binnen_scope' => true,
        ];
    }

    /** Volledig geclassificeerd — de observer tilt de status daarmee op 'actief'. */
    public function geclassificeerd(): static
    {
        return $this->state(fn () => [
            'vertrouwelijkheidsniveau' => 'intern',
            'integriteitsniveau' => 'intern',
            'beschikbaarheidsniveau' => 'normaal',
        ]);
    }
}
