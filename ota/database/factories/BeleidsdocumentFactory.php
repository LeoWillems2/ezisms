<?php

namespace Database\Factories;

use App\Models\Beleidsdocument;
use App\Models\OrganisatieEenheid;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Beleidsdocument> */
class BeleidsdocumentFactory extends Factory
{
    protected $model = Beleidsdocument::class;

    public function definition(): array
    {
        return [
            'titel' => 'Informatiebeveiligingsbeleid '.fake()->unique()->numberBetween(1, 9999),
            'type' => 'beleid',
            'omschrijving' => fake()->sentence(),
            'status' => 'concept',
            'leesbevestiging_vereist' => true,
        ];
    }

    /** Onderwerpspecifieke beleidsregel: standaard geen bevestigingsplicht (§6). */
    public function procedure(): static
    {
        return $this->state(fn () => [
            'titel' => 'Procedure '.fake()->unique()->numberBetween(1, 9999),
            'type' => 'procedure',
            'leesbevestiging_vereist' => false,
        ]);
    }

    /** Richt de bevestigingsplicht op de opgegeven afdelingen — die vormen de
     *  doelgroep (§6). */
    public function voorAfdelingen(OrganisatieEenheid ...$afdelingen): static
    {
        return $this->afterCreating(function (Beleidsdocument $document) use ($afdelingen) {
            $document->afdelingen()->attach(collect($afdelingen)->pluck('id'));
        });
    }
}
