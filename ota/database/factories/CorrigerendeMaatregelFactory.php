<?php

namespace Database\Factories;

use App\Models\Afwijking;
use App\Models\CorrigerendeMaatregel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CorrigerendeMaatregel> */
class CorrigerendeMaatregelFactory extends Factory
{
    protected $model = CorrigerendeMaatregel::class;

    public function definition(): array
    {
        return [
            'afwijking_id' => Afwijking::factory(),
            'omschrijving' => fake()->sentence(),
            'deadline' => now()->addDays(14),
            'status' => 'open',
        ];
    }

    public function voltooid(): static
    {
        return $this->state(fn () => ['status' => 'voltooid', 'voltooid_op' => now()]);
    }
}
