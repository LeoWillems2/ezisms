<?php

namespace Database\Factories;

use App\Models\Maatregel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — de echte 93 maatregelen komen uit
 * MaatregelSeeder. Die leest een bestand met gelicentieerde normtekst dat niet
 * in versiebeheer staat, dus de testsuite mag er niet van afhangen.
 *
 * @extends Factory<Maatregel>
 */
class MaatregelFactory extends Factory
{
    protected $model = Maatregel::class;

    public function definition(): array
    {
        return [
            'annex_a_referentie' => fake()->unique()->numerify('5.##'),
            'thema' => fake()->randomElement(['organisatorisch', 'mensgericht', 'fysiek', 'technologisch']),
            'naam' => fake()->words(3, true),
            'omschrijving' => fake()->sentence(),
        ];
    }

    /** Maatregel inclusief de bijbehorende (nog onbesliste) SoA-regel. */
    public function metSoaRegel(): static
    {
        return $this->afterCreating(fn (Maatregel $maatregel) => $maatregel->soaRegel()->create());
    }
}
