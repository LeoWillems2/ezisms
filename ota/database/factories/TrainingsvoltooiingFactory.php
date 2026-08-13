<?php

namespace Database\Factories;

use App\Models\Gebruiker;
use App\Models\Trainingsmodule;
use App\Models\Trainingsvoltooiing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Trainingsvoltooiing>
 */
class TrainingsvoltooiingFactory extends Factory
{
    protected $model = Trainingsvoltooiing::class;

    public function definition(): array
    {
        return [
            'trainingsmodule_id' => Trainingsmodule::factory(),
            'gebruiker_id' => Gebruiker::factory(),
            'voltooid_op' => Carbon::today(),
            'verloopt_op' => Carbon::today()->addMonths(12),
            'bron' => 'zelfregistratie',
        ];
    }

    public function verlopen(): static
    {
        return $this->state([
            'voltooid_op' => Carbon::today()->subMonths(13),
            'verloopt_op' => Carbon::today()->subMonth(),
        ]);
    }

    public function viaToets(): static
    {
        return $this->state(['bron' => 'toets']);
    }
}
