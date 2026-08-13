<?php

namespace Database\Factories;

use App\Models\Taak;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Taak>
 */
class TaakFactory extends Factory
{
    protected $model = Taak::class;

    public function definition(): array
    {
        return [
            'titel' => fake()->sentence(4),
            'deadline' => now()->addDays(30),
            'status' => 'open',
        ];
    }

    public function verstreken(int $dagen = 1): static
    {
        return $this->state(fn () => ['deadline' => now()->subDays($dagen)]);
    }

    /**
     * Een losse stap in een reeks, voor tests die een reeks niet via
     * `Stappenreeks::start()` opbouwen. De koppeling aan het dossier zet je er
     * zelf bij; zonder die koppeling is het geen reeks.
     */
    public function stap(int $volgorde, string $status = 'wachtend'): static
    {
        return $this->state(fn () => ['volgorde' => $volgorde, 'status' => $status]);
    }
}
