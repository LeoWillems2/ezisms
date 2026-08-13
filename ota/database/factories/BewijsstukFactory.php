<?php

namespace Database\Factories;

use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Support\Bewijsopslag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata. Schrijft géén bestand
 * naar schijf; tests die de download of de integriteitscheck raken gebruiken
 * Storage::fake() plus Bewijsopslag::bewaar().
 *
 * @extends Factory<Bewijsstuk>
 */
class BewijsstukFactory extends Factory
{
    protected $model = Bewijsstuk::class;

    public function definition(): array
    {
        $naam = fake()->words(3, true);

        return [
            'naam' => $naam,
            'omschrijving' => fake()->sentence(),
            'bestandsnaam' => str($naam)->slug().'.pdf',
            'bestandstype' => 'application/pdf',
            'bestandsgrootte' => fake()->numberBetween(1024, 5_000_000),
            'opslaglocatie_referentie' => 'test/'.fake()->uuid().'.pdf',
            'bestandshash' => hash('sha256', fake()->uuid()),
            'geupload_door' => Gebruiker::factory(),
            'geupload_op' => now(),
            'bewaren_tot' => now()->addYears(Bewijsopslag::BEWAARJAREN),
        ];
    }

    public function gearchiveerd(): static
    {
        return $this->state(fn () => ['status' => 'gearchiveerd']);
    }
}
