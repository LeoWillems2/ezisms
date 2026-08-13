<?php

namespace App\Console\Commands;

use App\Models\Maatregel;
use App\Support\Maatregelkenmerken;
use Illuminate\Console\Command;

/**
 * Zet de dimensie `capaciteiten` van de maatregelclassificatie aan of uit.
 *
 * ISO 27002 kent vijf attribuutdimensies; dit ISMS levert er vier. Van
 * `capaciteiten` zijn zowel de waardenlijst als de toewijzing per maatregel
 * ISO-eigen, dus die horen niet in een distribueerbaar repo. Wie de norm bezit
 * mag ze in de eigen installatie wél gebruiken — dit commando is daarvoor de
 * ondersteunde weg, in plaats van met de hand aan de config en de database.
 *
 * Het zet beide stappen die nodig zijn, want los van elkaar doen ze niets:
 *  1. de schakelaar `ISMS_CAPACITEITEN` in `.env` (bepaalt of de dimensie
 *     überhaupt bestaat voor weergave en validatie);
 *  2. de waarden per maatregel in `maatregelen.kenmerken`.
 * En het leegt daarna de configcache, anders werkt stap 1 pas na de volgende
 * `config:clear`.
 *
 * De data komt uit `database/seeders/data/maatregel-capaciteiten.json`, dat
 * gitignored is — zelfde regime als `controls.json`. Verwacht formaat:
 *
 *     {
 *       "vocabulaire": ["Governance", "Veilige configuratie", ...],
 *       "regels": [ {"annex_a_referentie": "5.1", "capaciteiten": ["Governance"]}, ... ]
 *     }
 *
 * `uit` haalt de waarden weer uit de database en zet de schakelaar om; het
 * bronbestand blijft staan, zodat opnieuw aanzetten één commando is.
 */
class Capaciteiten extends Command
{
    protected $signature = 'isms:capaciteiten
        {stand=status : aan, uit of status}';

    protected $description = 'Zet de dimensie capaciteiten aan of uit (alleen zinvol als je ISO 27002 bezit)';

    private const DIMENSIE = 'capaciteiten';

    private const SCHAKELAAR = 'ISMS_CAPACITEITEN';

    public function handle(): int
    {
        return match ($this->argument('stand')) {
            'aan' => $this->aanzetten(),
            'uit' => $this->uitzetten(),
            'status' => $this->status(),
            default => $this->ongeldigeStand(),
        };
    }

    private function ongeldigeStand(): int
    {
        $this->error("Onbekende stand '{$this->argument('stand')}'. Gebruik: aan, uit of status.");

        return self::FAILURE;
    }

    private function status(): int
    {
        $actief = Maatregelkenmerken::isActief(self::DIMENSIE);
        $gevuld = Maatregel::whereNotNull('kenmerken')->get()
            ->filter(fn (Maatregel $m) => ! empty($m->kenmerken[self::DIMENSIE]))
            ->count();
        $bron = Maatregelkenmerken::bronpad(self::DIMENSIE);

        $this->line('Schakelaar   : '.($actief ? 'aan' : 'uit'));
        $this->line('Bronbestand  : '.(is_file($bron) ? $bron : "ontbreekt ({$bron})"));
        $this->line("Gevuld       : {$gevuld} maatregelen");

        if ($actief && $gevuld === 0) {
            $this->warn('De dimensie staat aan maar is nergens gevuld — dan toont het scherm niets.');
        }

        if (! $actief && $gevuld > 0) {
            $this->warn('Er staat data in de database die niemand ziet. Gebruik "uit" om die op te ruimen.');
        }

        return self::SUCCESS;
    }

    private function aanzetten(): int
    {
        $bron = Maatregelkenmerken::bronpad(self::DIMENSIE);

        if (! is_file($bron)) {
            $this->error("Geen bronbestand: {$bron}");
            $this->line('Dit ISMS levert de capaciteiten-toewijzing niet mee — die is ISO-eigen.');
            $this->line('Maak het bestand zelf aan op basis van je eigen exemplaar van ISO 27002,');
            $this->line('met de sleutels "vocabulaire" en "regels". Het is gitignored.');

            return self::FAILURE;
        }

        try {
            $data = json_decode(file_get_contents($bron), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->error("Bronbestand is geen geldige JSON: {$e->getMessage()}");

            return self::FAILURE;
        }

        $vocabulaire = $data['vocabulaire'] ?? [];
        $regels = $data['regels'] ?? [];

        if ($vocabulaire === [] || $regels === []) {
            $this->error('Bronbestand mist "vocabulaire" of "regels".');

            return self::FAILURE;
        }

        // Eerst volledig valideren, dan pas schrijven: een half toegepaste
        // toewijzing is lastiger te herkennen dan een geweigerde.
        $fouten = [];
        foreach ($regels as $i => $regel) {
            $ref = $regel['annex_a_referentie'] ?? "(regel {$i})";
            $waarden = $regel[self::DIMENSIE] ?? [];

            if ($waarden === []) {
                $fouten[] = "A.{$ref} heeft geen capaciteiten";

                continue;
            }

            $onbekend = array_diff($waarden, $vocabulaire);
            if ($onbekend !== []) {
                $fouten[] = "A.{$ref} gebruikt waarden buiten het vocabulaire: ".implode(', ', $onbekend);
            }
        }

        if ($fouten !== []) {
            $this->error('Bronbestand niet consistent:');
            foreach (array_slice($fouten, 0, 10) as $fout) {
                $this->line("  * {$fout}");
            }
            if (count($fouten) > 10) {
                $this->line('  * ... en '.(count($fouten) - 10).' meer');
            }

            return self::FAILURE;
        }

        $geschreven = 0;
        $onbekendeReferenties = [];

        foreach ($regels as $regel) {
            $maatregel = Maatregel::where('annex_a_referentie', $regel['annex_a_referentie'])->first();

            if ($maatregel === null) {
                $onbekendeReferenties[] = $regel['annex_a_referentie'];

                continue;
            }

            $maatregel->update([
                'kenmerken' => array_merge(
                    $maatregel->kenmerken ?? [],
                    [self::DIMENSIE => array_values($regel[self::DIMENSIE])],
                ),
            ]);

            $geschreven++;
        }

        $this->schrijfSchakelaar(true);

        $this->info("Capaciteiten aangezet en op {$geschreven} maatregelen gezet.");

        if ($onbekendeReferenties !== []) {
            $this->warn('Geen maatregel gevonden voor: '.implode(', ', $onbekendeReferenties));
        }

        return self::SUCCESS;
    }

    private function uitzetten(): int
    {
        $opgeruimd = 0;

        foreach (Maatregel::whereNotNull('kenmerken')->get() as $maatregel) {
            $kenmerken = $maatregel->kenmerken;

            if (! array_key_exists(self::DIMENSIE, $kenmerken)) {
                continue;
            }

            unset($kenmerken[self::DIMENSIE]);
            $maatregel->update(['kenmerken' => $kenmerken]);
            $opgeruimd++;
        }

        $this->schrijfSchakelaar(false);

        $this->info("Capaciteiten uitgezet en van {$opgeruimd} maatregelen verwijderd.");
        $this->line('Het bronbestand blijft staan; opnieuw aanzetten is één commando.');

        return self::SUCCESS;
    }

    /**
     * Zet de schakelaar in `.env` en leegt de configcache, zodat de wijziging
     * meteen geldt. Ontbreekt `.env`, dan alleen melden wat er handmatig moet —
     * zwijgend niets doen zou de indruk wekken dat het gelukt is.
     */
    private function schrijfSchakelaar(bool $aan): void
    {
        $waarde = $aan ? 'true' : 'false';
        $regel = self::SCHAKELAAR.'='.$waarde;
        $pad = app()->environmentFilePath();

        if (! is_file($pad)) {
            $this->warn("Geen .env gevonden op {$pad} — zet zelf: {$regel}");

            return;
        }

        $inhoud = file_get_contents($pad);
        $patroon = '/^'.preg_quote(self::SCHAKELAAR, '/').'=.*$/m';

        $nieuw = preg_match($patroon, $inhoud) === 1
            ? preg_replace($patroon, $regel, $inhoud)
            : rtrim($inhoud, "\n")."\n\n{$regel}\n";

        file_put_contents($pad, $nieuw);

        // De config is al geladen in dit proces; bijwerken zodat een
        // vervolgstap in dezelfde run de nieuwe stand ziet.
        config(['maatregelkenmerken.'.self::DIMENSIE.'.actief' => $aan]);
        Maatregelkenmerken::vergeetBron();

        $this->call('config:clear');
    }
}
