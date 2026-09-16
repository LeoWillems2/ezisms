<?php

namespace App\Console\Commands;

use App\Models\Gebruiker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Zet een extern account terug op inloggen met een wachtwoord
 * (implementatie/01j §9.2).
 *
 * Alleen vanaf de commandoregel, en dat is een keuze. Deze weg is nodig als de
 * identiteitsprovider wegvalt: een verlopen secret, een beëindigd tenant, een
 * storing. Dan kan de CISO vaak zelf niet inloggen, en wie de configuratie van de
 * installatie repareert, heeft de commandoregel — dezelfde redenering als bij
 * `isms:tweefactor-resetten`.
 */
class InlogmethodeWachtwoord extends Command
{
    protected $signature = 'isms:inlogmethode-wachtwoord {email}';

    protected $description = 'Zet een account dat via de identiteitsprovider inlogt terug op een wachtwoord';

    public function handle(): int
    {
        $gebruiker = Gebruiker::where('email', Str::lower(trim((string) $this->argument('email'))))->first();

        if ($gebruiker === null) {
            $this->error('Geen account gevonden met dit e-mailadres.');

            return self::FAILURE;
        }

        if (! $gebruiker->isExtern()) {
            $this->info("{$gebruiker->naam} logt al in met een wachtwoord; niets te doen.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($gebruiker) {
            $gebruiker->externeIdentiteit()->first()?->delete();

            $gebruiker->update([
                'inlogmethode' => 'wachtwoord',
                'koppeling_uitgereikt_op' => null,
                'wachtwoord' => Str::random(32),
            ]);

            // Was de IdP vrijgesteld van de tweede factor, dan geldt de plicht nu
            // weer — met respijt, niet met een deadline die al verstreken is.
            if ($gebruiker->tweefactorVereist() && ! $gebruiker->tweefactorActief()) {
                $gebruiker->forceFill([
                    'tweefactor_deadline' => now()->addDays(config('tweefactor.respijt_dagen')),
                ])->save();
            }

            // Er is geen ingelogde gebruiker, dus de trail schrijft "Systeem
            // (geplande taak)". Vandaar de toevoeging in de waarde zelf.
            $gebruiker->schrijfAuditregel('gewijzigd', oud: null, nieuw: [
                'inlogmethode' => 'naar wachtwoord vanaf de commandoregel',
            ]);
        });

        // Het herstelformulier bestaat al en werkt voor een actief
        // wachtwoordaccount; er is geen mailkanaal voor nodig.
        $token = Password::broker()->createToken($gebruiker);
        $link = route('password.reset', ['token' => $token, 'email' => $gebruiker->email]);

        $this->info("{$gebruiker->naam} logt voortaan in met een wachtwoord.");
        $this->line('Reik deze link uit om een wachtwoord in te stellen (geldig '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minuten):');
        $this->line($link);
        $this->warn('Behandel deze link als een wachtwoord.');

        return self::SUCCESS;
    }
}
