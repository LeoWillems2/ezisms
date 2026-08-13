<?php

namespace Database\Factories;

use App\Models\Afwijking;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Afwijking> */
class AfwijkingFactory extends Factory
{
    protected $model = Afwijking::class;

    public function definition(): array
    {
        return [
            'bron' => 'interne_signalering',
            'omschrijving' => fake()->sentence(),
            'status' => 'open',
        ];
    }
}
