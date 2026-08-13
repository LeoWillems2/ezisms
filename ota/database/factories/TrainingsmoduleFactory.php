<?php

namespace Database\Factories;

use App\Models\Trainingsmodule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Trainingsmodule>
 */
class TrainingsmoduleFactory extends Factory
{
    protected $model = Trainingsmodule::class;

    public function definition(): array
    {
        return [
            'titel' => fake()->sentence(3),
            'toets_bestand' => null,
            'geldigheidsduur_maanden' => 12,
            'actief' => true,
        ];
    }

    public function eenmalig(): static
    {
        return $this->state(['geldigheidsduur_maanden' => null]);
    }

    public function metToets(string $bestand = 't1.html'): static
    {
        return $this->state(['toets_bestand' => $bestand]);
    }

    public function ingetrokken(): static
    {
        return $this->state(['actief' => false]);
    }
}
