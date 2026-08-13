<?php

namespace Database\Factories;

use App\Models\Belanghebbende;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Belanghebbende>
 */
class BelanghebbendeFactory extends Factory
{
    protected $model = Belanghebbende::class;

    public function definition(): array
    {
        return [
            'naam' => fake()->randomElement(['Klant', 'Toezichthouder', 'Aandeelhouder', 'Medewerker', 'Leverancier']),
            'aard' => fake()->randomElement(['intern', 'extern']),
            'relevantie_voor_isms' => fake()->sentence(),
        ];
    }
}
