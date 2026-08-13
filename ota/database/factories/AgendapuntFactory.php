<?php

namespace Database\Factories;

use App\Models\Agendapunt;
use App\Models\Reviewsessie;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agendapunt> */
class AgendapuntFactory extends Factory
{
    protected $model = Agendapunt::class;

    public function definition(): array
    {
        return [
            'reviewsessie_id' => Reviewsessie::factory(),
            'categorie' => 'kpi_resultaten',
            'samenvatting' => fake()->sentence(),
            'gekoppeld_blok_naam' => null,
        ];
    }
}
