<?php

namespace Database\Factories;

use App\Models\Auditprogramma;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Auditprogramma> */
class AuditprogrammaFactory extends Factory
{
    protected $model = Auditprogramma::class;

    public function definition(): array
    {
        return [
            'naam' => 'Interne auditcyclus '.$this->faker->year(),
            'start_datum' => '2026-01-01',
            'aard' => 'certificeringscyclus',
            'aantal_jaren' => 3,
            'status' => 'actief',
        ];
    }
}
