<?php

namespace Database\Factories;

use App\Models\Besluit;
use App\Models\Reviewsessie;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Besluit> */
class BesluitFactory extends Factory
{
    protected $model = Besluit::class;

    public function definition(): array
    {
        return [
            'reviewsessie_id' => Reviewsessie::factory(),
            'omschrijving' => fake()->sentence(),
        ];
    }
}
