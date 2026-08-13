<?php

namespace Database\Factories;

use App\Models\Auditplan;
use App\Models\Auditronde;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Auditronde> */
class AuditrondeFactory extends Factory
{
    protected $model = Auditronde::class;

    public function definition(): array
    {
        return [
            'auditplan_id' => Auditplan::factory(),
            'type' => 'intern',
            'gepland_op' => now()->toDateString(),
            'status' => 'gepland',
        ];
    }
}
