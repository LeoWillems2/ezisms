<?php

namespace Database\Factories;

use App\Models\IntegratieAdapter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IntegratieAdapter> */
class IntegratieAdapterFactory extends Factory
{
    protected $model = IntegratieAdapter::class;

    public function definition(): array
    {
        return [
            'naam' => fake()->company(),
            'type' => fake()->randomElement(['identiteit', 'ticketing', 'scanning', 'overig']),
            'status' => 'niet_geconfigureerd',
            'laatste_synchronisatie_op' => null,
        ];
    }
}
