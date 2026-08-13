<?php

namespace Database\Factories;

use App\Models\Auditronde;
use App\Models\Bevinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bevinding> */
class BevindingFactory extends Factory
{
    protected $model = Bevinding::class;

    public function definition(): array
    {
        return [
            'auditronde_id' => Auditronde::factory(),
            'type' => 'observatie',
            'omschrijving' => fake()->sentence(),
            'status' => 'open',
        ];
    }
}
