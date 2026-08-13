<?php

namespace Database\Factories;

use App\Models\Gebruiker;
use App\Models\OrganisatieEenheid;
use App\Models\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata.
 *
 * @extends Factory<Gebruiker>
 */
class GebruikerFactory extends Factory
{
    protected $model = Gebruiker::class;

    public function definition(): array
    {
        return [
            'naam' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'wachtwoord' => 'wachtwoord',
            'status' => 'actief',
            'vervalt_op' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function uitgenodigd(): static
    {
        return $this->state(fn () => ['status' => 'uitgenodigd']);
    }

    public function geblokkeerd(): static
    {
        return $this->state(fn () => ['status' => 'geblokkeerd']);
    }

    public function gedeactiveerd(): static
    {
        return $this->state(fn () => ['status' => 'gedeactiveerd']);
    }

    /** Plaatst de gebruiker op een afdeling — nodig om tot een doelgroep van
     *  een leesbevestiging te horen (§6). */
    public function opAfdeling(OrganisatieEenheid|int $afdeling): static
    {
        return $this->state(fn () => [
            'organisatie_eenheid_id' => $afdeling instanceof OrganisatieEenheid ? $afdeling->id : $afdeling,
        ]);
    }

    /** Wijst na het aanmaken de rol met de opgegeven naam toe. */
    public function metRol(string $rolNaam): static
    {
        return $this->afterCreating(function ($gebruiker) use ($rolNaam) {
            $rol = Rol::where('naam', $rolNaam)->firstOrFail();

            $gebruiker->rolToewijzingen()->create([
                'rol_id' => $rol->id,
                'toegekend_op' => now(),
            ]);
        });
    }
}
