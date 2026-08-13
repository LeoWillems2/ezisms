<?php

namespace Database\Factories;

use App\Models\Risico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Risico>
 */
class RisicoFactory extends Factory
{
    protected $model = Risico::class;

    public function definition(): array
    {
        return [
            'titel' => fake()->sentence(4),
            'dreiging' => fake()->sentence(),
            'kwetsbaarheid' => fake()->sentence(),
            'status' => 'geidentificeerd',
        ];
    }

    /** De observer leidt de risicoscore af uit kans x impact. */
    public function beoordeeld(int $kans = 3, int $impact = 3): static
    {
        return $this->state(fn () => [
            'kans_niveau' => $kans,
            'impact_niveau' => $impact,
            'status' => 'beoordeeld',
        ]);
    }
}
