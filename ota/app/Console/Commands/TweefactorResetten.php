<?php

namespace App\Console\Commands;

use App\Models\Gebruiker;
use Illuminate\Console\Command;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

/**
 * Zet de tweede factor van één account terug (implementatie/01d §8).
 *
 * Dit is geen extraatje naast de knop in het gebruikersoverzicht. De CISO die
 * zijn telefoon én zijn herstelcodes kwijt is, kan zichzelf niet resetten via
 * een scherm waar hij niet in komt. Zonder dit commando is de enige uitweg een
 * handmatige `UPDATE` op de database — precies het soort ingreep dat buiten
 * elke logging omgaat.
 */
class TweefactorResetten extends Command
{
    protected $signature = 'isms:tweefactor-resetten {email}';

    protected $description = 'Zet de tweefactorauthenticatie van een account terug';

    public function handle(DisableTwoFactorAuthentication $uitzetten): int
    {
        $gebruiker = Gebruiker::where('email', $this->argument('email'))->first();

        if ($gebruiker === null) {
            $this->error('Geen account gevonden met dit e-mailadres.');

            return self::FAILURE;
        }

        if (! $gebruiker->tweefactorActief() && $gebruiker->two_factor_secret === null) {
            $this->info("{$gebruiker->naam} heeft geen tweefactor ingesteld; niets te doen.");

            return self::SUCCESS;
        }

        $uitzetten($gebruiker);

        $gebruiker->forceFill([
            'tweefactor_deadline' => now()->addDays(config('tweefactor.respijt_dagen')),
        ])->save();

        // Er is geen ingelogde gebruiker, dus de trail schrijft "Systeem
        // (geplande taak)". Dat is hier niet de hele waarheid — vandaar de
        // toevoeging in de waarde zelf, zodat een auditor ziet dat dit vanaf de
        // commandoregel gebeurde en niet vanuit een scherm.
        $gebruiker->schrijfAuditregel('gewijzigd', oud: null, nieuw: [
            'tweefactor' => 'gereset vanaf de commandoregel',
        ]);

        $this->info("Tweefactor van {$gebruiker->naam} is teruggezet. Bij de volgende login volgt opnieuw de instelprocedure.");
        $this->line('Respijt tot '.$gebruiker->tweefactor_deadline->format('d-m-Y').'.');

        return self::SUCCESS;
    }
}
