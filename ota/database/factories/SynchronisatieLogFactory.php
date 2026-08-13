<?php

namespace Database\Factories;

use App\Models\IntegratieAdapter;
use App\Models\SynchronisatieLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SynchronisatieLog> */
class SynchronisatieLogFactory extends Factory
{
    protected $model = SynchronisatieLog::class;

    public function definition(): array
    {
        return [
            'integratie_adapter_id' => IntegratieAdapter::factory(),
            'tijdstip' => now(),
            'resultaat' => 'succes',
            'aantal_verwerkte_records' => fake()->numberBetween(0, 500),
        ];
    }
}
