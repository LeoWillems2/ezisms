<?php

namespace Database\Factories;

use App\Models\Sjabloonstap;
use App\Models\Wijzigingssjabloon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Sjabloonstap>
 */
class SjabloonstapFactory extends Factory
{
    protected $model = Sjabloonstap::class;

    public function definition(): array
    {
        return [
            'wijzigingssjabloon_id' => Wijzigingssjabloon::factory(),
            'volgorde' => 1,
            'titel' => fake()->sentence(3),
            'staptype' => 'analyse',
            'deadline_offset_dagen' => 0,
            'bewijs_verplicht' => false,
        ];
    }

    public function type(string $staptype, int $volgorde, int $offset = 0): static
    {
        return $this->state(fn () => [
            'staptype' => $staptype,
            'volgorde' => $volgorde,
            'deadline_offset_dagen' => $offset,
        ]);
    }
}
