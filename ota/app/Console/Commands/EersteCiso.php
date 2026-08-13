<?php

namespace App\Console\Commands;

use App\Models\Gebruiker;
use App\Models\Rol;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Bootstrap van het allereerste CISO-account. Kan niet via /gebruikers, want
 * die pagina vereist al een ingelogde CISO (implementatie/01-identity-access.md §6).
 *
 * E-mailadres en wachtwoord geef je op de commandline mee; het account is
 * meteen 'actief' en kan direct inloggen. Zo is er geen mailserver nodig voor
 * deze eenmalige stap. De naam is optioneel en wordt anders uit het adres
 * afgeleid; de CISO kan die later in /gebruikers bijstellen.
 */
class EersteCiso extends Command
{
    protected $signature = 'isms:eerste-ciso {email} {wachtwoord} {naam?}';

    protected $description = 'Maakt het eerste, direct actieve CISO-account aan';

    public function handle(): int
    {
        $email = $this->argument('email');
        $wachtwoord = $this->argument('wachtwoord');

        $validatie = Validator::make(
            ['email' => $email, 'wachtwoord' => $wachtwoord],
            [
                'email' => ['required', 'email'],
                // Dezelfde eis als elk scherm: de ondergrens staat in
                // AppServiceProvider, niet hier.
                'wachtwoord' => ['required', 'string', Password::defaults()],
            ],
        );

        if ($validatie->fails()) {
            foreach ($validatie->errors()->all() as $fout) {
                $this->error($fout);
            }

            return self::FAILURE;
        }

        if (Gebruiker::where('email', $email)->exists()) {
            $this->error("Er bestaat al een gebruiker met e-mailadres {$email}.");

            return self::FAILURE;
        }

        $rol = Rol::where('naam', 'CISO')->first();

        if (! $rol) {
            $this->error('De CISO-rol ontbreekt. Draai eerst `php artisan db:seed`.');

            return self::FAILURE;
        }

        $gebruiker = Gebruiker::create([
            // "leo@lewi.nl" -> "Leo" als er geen naam is meegegeven.
            'naam' => $this->argument('naam') ?: Str::of($email)->before('@')->headline(),
            'email' => $email,
            'wachtwoord' => $wachtwoord, // de 'hashed'-cast hasht dit bij opslaan
            'status' => 'actief',
        ]);

        $gebruiker->rolToewijzingen()->create([
            'rol_id' => $rol->id,
            'toegekend_door_id' => null, // bootstrap: geen toekennende gebruiker
            'toegekend_op' => now(),
        ]);

        $this->info("Actief CISO-account aangemaakt voor {$gebruiker->naam} <{$gebruiker->email}>. Je kunt direct inloggen.");

        return self::SUCCESS;
    }
}
