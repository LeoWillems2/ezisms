<?php

namespace Database\Factories;

use App\Models\Afwijking;
use App\Models\Grondoorzaak;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Grondoorzaak> */
class GrondoorzaakFactory extends Factory
{
    protected $model = Grondoorzaak::class;

    public function definition(): array
    {
        return [
            'afwijking_id' => Afwijking::factory(),
            'omschrijving' => fake()->sentence(),
            'methodiek' => '5x waarom',
        ];
    }
}
