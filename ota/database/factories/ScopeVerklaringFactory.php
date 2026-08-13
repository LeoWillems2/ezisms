<?php

namespace Database\Factories;

use App\Models\ScopeVerklaring;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<ScopeVerklaring>
 */
class ScopeVerklaringFactory extends Factory
{
    protected $model = ScopeVerklaring::class;

    public function definition(): array
    {
        return [
            'versienummer' => 1,
            'scopetekst' => fake()->paragraph(),
            'status' => 'concept',
            'geldig_vanaf' => null,
            'goedgekeurd_door' => null,
            'volgende_herziening_gepland' => null,
        ];
    }

    public function actief(): static
    {
        return $this->state(fn () => [
            'status' => 'actief',
            'geldig_vanaf' => now(),
            'goedgekeurd_door' => 'Directie',
            'volgende_herziening_gepland' => now()->addYear(),
        ]);
    }

    public function terGoedkeuring(): static
    {
        return $this->state(fn () => ['status' => 'ter_goedkeuring']);
    }

    public function vervangen(): static
    {
        return $this->state(fn () => ['status' => 'vervangen']);
    }
}
