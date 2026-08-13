<?php

namespace Database\Factories;

use App\Models\Besluit;
use App\Models\Verbeteractie;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Verbeteractie> */
class VerbeteractieFactory extends Factory
{
    protected $model = Verbeteractie::class;

    public function definition(): array
    {
        return [
            'besluit_id' => Besluit::factory(),
            'omschrijving' => fake()->sentence(),
            'status' => 'open',
        ];
    }
}
