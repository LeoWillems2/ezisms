<?php

namespace Database\Factories;

use App\Models\Gebruiker;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Incident> */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'titel' => 'Incident '.fake()->unique()->numberBetween(1, 9999),
            'omschrijving' => fake()->sentence(),
            'gemeld_door_id' => Gebruiker::factory(),
            'gemeld_op' => now(),
            'ernst' => 'midden',
            'status' => 'gemeld',
        ];
    }

    public function kritiek(): static
    {
        return $this->state(fn () => ['ernst' => 'kritiek']);
    }

    /**
     * Meldplicht beoordeeld: geen raakvlak met een van beide wetten.
     *
     * Sinds implementatie/08b blokkeert een onbeoordeelde meldplicht het sluiten
     * van een incident. Tests die over iets anders gaan dan die beoordeling
     * gebruiken deze state om de voorwaarde af te vinken.
     *
     * Geen motivatie: zonder raakvlak is er geen documentatieplicht, en twee
     * keer "nee" is een volledig antwoord.
     */
    public function meldplichtBeoordeeld(): static
    {
        return $this->state(fn () => [
            'kennisname_op' => now(),
            'raakt_persoonsgegevens' => false,
            'is_netwerk_informatie_incident' => config('meldplicht.cbw_plichtig') ? false : null,
            'extern_meldingsplichtig' => false,
            'meldplicht_beoordeeld_op' => now(),
        ]);
    }
}
