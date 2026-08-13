<?php

namespace Database\Factories;

use App\Models\Wijziging;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Wijziging>
 */
class WijzigingFactory extends Factory
{
    protected $model = Wijziging::class;

    public function definition(): array
    {
        return [
            'titel' => 'Upgrade '.fake()->word(),
            'soort' => 'leveranciersrelease',
            'zwaarte' => 'standaard',
            'status' => 'aangemeld',
            'aangekondigd_op' => now()->subDays(7),
        ];
    }

    /** Met een ingevuld terugvalplan, zodat een uitvoerstap niet blokkeert. */
    public function metTerugvalplan(): static
    {
        return $this->state(fn () => [
            'terugvalplan' => 'Terugzetten van de snapshot van vóór de upgrade.',
        ]);
    }
}
