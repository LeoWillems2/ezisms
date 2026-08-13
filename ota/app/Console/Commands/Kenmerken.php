<?php

namespace App\Console\Commands;

use App\Models\Gebruiker;
use App\Models\Maatregel;
use App\Models\SoaRegel;
use App\Support\TaakPlanner;
use Database\Seeders\MaatregelKenmerkenSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Leest de meegeleverde uitgangsclassificatie opnieuw in en zorgt dat een
 * *wijziging* daarin niet ongemerkt langs de organisatie gaat
 * (implementatie/04f-maatregelseeds-vereenvoudigen.md §4.1).
 *
 * De classificaties in `maatregel-kenmerken.json` zijn een uitgangspunt, geen
 * normtabel: `soa_regels.kenmerken_eigen` zet de eigen vaststelling van de
 * organisatie ernaast in plaats van eroverheen, en `SoaRegel` is auditeerbaar.
 * Precies daarom hoeft die uitgangswaarde geen zware herkomstclaim te dragen —
 * een fout erin is per regel corrigeerbaar, met vastlegging dat het gebeurd is.
 *
 * Die constructie heeft één gat, en dit commando dicht het. `kenmerken_eigen` is
 * alles-of-niets (zie {@see SoaRegel::kenmerken()}): zodra een regel een eigen
 * classificatie heeft, bevriest die alle dimensies. Corrigeren wij later een
 * meegeleverde waarde, dan bereikt die correctie juist niet de regels waar
 * iemand heeft zitten opletten — en niemand ziet dat. Dus: eerst kijken wat er
 * wijzigt, dan seeden, dan een taak voor de regels die achterblijven.
 *
 * **Het aanmaken van die taken zit hier en niet in de seeder.** `DatabaseSeeder`
 * levert uitsluitend referentiedata; een taak is operationele data. Zou de
 * seeder taken aanmaken, dan deed `php artisan db:seed` dat voortaan ook — in de
 * testsuite en bij de demo-opbouw.
 *
 * Bij een eerste installatie doet deze situatie zich niet voor: er is dan geen
 * enkele SoA-regel met een eigen classificatie, dus er ontstaan nul taken. Dat
 * de eerste CISO pas ná het seeden bestaat (`isms:eerste-ciso`) is daarom geen
 * probleem. `deploy.sh` roept dit commando dan ook alleen aan op het releasepad.
 */
class Kenmerken extends Command
{
    protected $signature = 'isms:kenmerken
        {--controleer : alleen tonen wat er zou wijzigen, niets schrijven}';

    protected $description = 'Leest de uitgangsclassificatie in en signaleert SoA-regels die achterblijven';

    /** Idempotentiesleutel van de taak, samen met de SoA-regel. */
    public const SOORT = 'kenmerken-herijken';

    /** Termijn voor de controletaak. Er is geen externe termijn; dit is eigen werk. */
    private const TERMIJN_DAGEN = 30;

    public function handle(): int
    {
        $gewijzigd = $this->gewijzigdeMaatregelen();

        if ($this->option('controleer')) {
            $this->line(count($gewijzigd).' maatregelen zouden een andere uitgangsclassificatie krijgen.');
            $this->line($this->achterblijvers($gewijzigd)->count().' SoA-regels met een eigen classificatie '
                .'zouden die wijziging niet volgen.');

            return self::SUCCESS;
        }

        $this->callSilent('db:seed', ['--class' => MaatregelKenmerkenSeeder::class, '--force' => true]);

        $achterblijvers = $this->achterblijvers($gewijzigd);
        $eigenaar = $this->ciso();

        foreach ($achterblijvers as $regel) {
            TaakPlanner::planVoorEntiteit(
                $regel,
                self::SOORT,
                "Controleer de classificatie van A.{$regel->maatregel->annex_a_referentie}",
                now()->addDays(self::TERMIJN_DAGEN),
                'soa',
                $eigenaar?->id,
            );
        }

        // Ook bij nul: de beheerder hoort te zien dát er gekeken is. Stilte is
        // niet te onderscheiden van een commando dat niet gedraaid heeft.
        $this->info($achterblijvers->count().' SoA-regels met een eigen classificatie volgen een '
            .'gewijzigd uitgangspunt niet'.($achterblijvers->isEmpty() ? '.' : ' — taken aangemaakt.'));

        if ($achterblijvers->isNotEmpty() && $eigenaar === null) {
            $this->warn('Geen actieve CISO gevonden; de taken staan zonder toegewezen eigenaar in het '
                .'takenoverzicht.');
        }

        return self::SUCCESS;
    }

    /**
     * De referenties waarvan de meegeleverde classificatie afwijkt van wat er nu
     * in de database staat — dus wat deze seedronde zou veranderen.
     *
     * Moet vóór het seeden gebeuren: daarna is de oude waarde weg en is er niets
     * meer te vergelijken.
     *
     * @return list<string>
     */
    private function gewijzigdeMaatregelen(): array
    {
        $pad = database_path('seeders/data/maatregel-kenmerken.json');

        if (! is_file($pad)) {
            $this->warn('Geen maatregel-kenmerken.json — niets te vergelijken.');

            return [];
        }

        $data = json_decode(file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR);
        $huidig = Maatregel::pluck('kenmerken', 'annex_a_referentie');
        $gewijzigd = [];

        foreach ($data['regels'] ?? [] as $regel) {
            $referentie = $regel['annex_a_referentie'];

            if (! $huidig->has($referentie)) {
                // Nog geen maatregel: dat is een nieuwe rij, geen wijziging van
                // een uitgangspunt waar iemand al naar gekeken kan hebben.
                continue;
            }

            // Alleen de dimensies die dit bestand levert. `capaciteiten` komt van
            // de installatie zelf (isms:capaciteiten) en hoort hier niet mee te
            // wegen — die staat wel in de database en niet in het bestand.
            $nieuw = $this->normaliseer($regel['kenmerken']);
            $oud = $this->normaliseer(array_intersect_key(
                $huidig->get($referentie) ?? [],
                $regel['kenmerken'],
            ));

            if ($nieuw !== $oud) {
                $gewijzigd[] = $referentie;
            }
        }

        return $gewijzigd;
    }

    /**
     * De SoA-regels die een eigen classificatie hebben bij een maatregel waarvan
     * het uitgangspunt wijzigt. Precies de regels waar iemand naar gekeken heeft
     * en waar de correctie dus niet vanzelf aankomt.
     *
     * @param  list<string>  $gewijzigd
     * @return Collection<int, SoaRegel>
     */
    private function achterblijvers(array $gewijzigd): Collection
    {
        if ($gewijzigd === []) {
            return SoaRegel::query()->whereIn('id', [])->get();
        }

        return SoaRegel::with('maatregel')
            ->whereNotNull('kenmerken_eigen')
            ->whereHas('maatregel', fn ($q) => $q->whereIn('annex_a_referentie', $gewijzigd))
            ->get();
    }

    /**
     * De eigenaar van de controletaken: de langst bestaande actieve CISO, of
     * niemand. `eigenaar_id` mag null zijn — dan staat de taak zonder toewijzing
     * in het algemene overzicht, en dat is een geldige stand en geen storing.
     */
    private function ciso(): ?Gebruiker
    {
        return Gebruiker::selecteerbaar()
            ->whereHas('rollen', fn ($q) => $q->where('naam', 'CISO'))
            ->orderBy('id')
            ->first();
    }

    /**
     * Volgorde binnen een dimensie zegt niets, en een lege dimensie is hetzelfde
     * als een ontbrekende. Zelfde regel als {@see SoaRegel::wijktAfVanUitgangspunt()};
     * zonder dit zou een herschikte lijst als wijziging tellen.
     *
     * @param  array<string, list<string>>  $kenmerken
     * @return array<string, list<string>>
     */
    private function normaliseer(array $kenmerken): array
    {
        $genormaliseerd = [];

        foreach ($kenmerken as $dimensie => $waarden) {
            $waarden = array_values(array_unique((array) $waarden));
            sort($waarden);

            if ($waarden !== []) {
                $genormaliseerd[$dimensie] = $waarden;
            }
        }

        ksort($genormaliseerd);

        return $genormaliseerd;
    }
}
