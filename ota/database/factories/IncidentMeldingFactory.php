<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentMelding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<IncidentMelding>
 */
class IncidentMeldingFactory extends Factory
{
    protected $model = IncidentMelding::class;

    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'grondslag' => 'avg',
            'fase' => 'melding',
            'meldtermijn_uren' => 72,
            'uiterlijk_op' => now()->addHours(72),
            'gemeld_op' => null,
        ];
    }

    /** Een verplichting zonder klok — AVG art. 34, of Cbw art. 29 lid 2 zolang het incident loopt. */
    public function zonderTermijn(): static
    {
        return $this->state(fn () => [
            'fase' => 'betrokkenen',
            'meldtermijn_uren' => null,
            'uiterlijk_op' => null,
        ]);
    }

    public function teLaat(): static
    {
        return $this->state(fn () => ['uiterlijk_op' => now()->subHours(2)]);
    }
}
