<?php

namespace Database\Factories;

use App\Models\Reviewsessie;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reviewsessie> */
class ReviewsessieFactory extends Factory
{
    protected $model = Reviewsessie::class;

    public function definition(): array
    {
        return [
            'datum' => now()->toDateString(),
            'deelnemers' => fake()->name().', '.fake()->name(),
            'status' => 'gepland',
        ];
    }
}
