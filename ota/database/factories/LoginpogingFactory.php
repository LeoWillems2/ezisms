<?php

namespace Database\Factories;

use App\Models\Loginpoging;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Loginpoging>
 */
class LoginpogingFactory extends Factory
{
    protected $model = Loginpoging::class;

    public function definition(): array
    {
        return [
            'gebruiker_id' => null,
            'email_ingevoerd' => fake()->safeEmail(),
            'tijdstip' => now(),
            'succesvol' => false,
            'ip_adres' => fake()->ipv4(),
        ];
    }
}
