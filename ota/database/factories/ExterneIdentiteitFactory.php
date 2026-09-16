<?php

namespace Database\Factories;

use App\Models\ExterneIdentiteit;
use App\Models\Gebruiker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<ExterneIdentiteit>
 */
class ExterneIdentiteitFactory extends Factory
{
    protected $model = ExterneIdentiteit::class;

    public function definition(): array
    {
        return [
            'gebruiker_id' => Gebruiker::factory()->state(['inlogmethode' => 'extern']),
            'issuer' => 'https://idp.voorbeeld.test',
            'subject' => (string) Str::uuid(),
            'idp_gebruikersnaam' => fake()->safeEmail(),
            'gekoppeld_op' => now(),
        ];
    }
}
