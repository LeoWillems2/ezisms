<?php

namespace App\Console\Commands;

use App\Models\Overheidsmaatregel;
use App\Models\OverheidsmaatregelBeoordeling;
use App\Support\Normprofiel;
use Database\Seeders\OverheidsmaatregelSeeder;
use Illuminate\Console\Command;
use JsonException;

/**
 * Leest de BIO-overheidsmaatregelen opnieuw in
 * (deelproducten/04b-bio-overheidsmaatregelen.md §3.4).
 *
 * Zelfde rolverdeling als `isms:maatregelen`: `db:seed` doet hetzelfde
 * schrijfwerk, dit commando controleert vóór het schrijft en rapporteert erna.
 * Dat is hier extra nodig, want dit is het commando dat een CISO draait ná het
 * vullen van `overheidsmaatregel-teksten.json` met de gedownloade BIO — en dan
 * moet een fout in dat bestand een leesbare melding geven en geen halve seed.
 */
class Overheidsmaatregelen extends Command
{
    protected $signature = 'isms:overheidsmaatregelen
        {--controleer : alleen controleren, niets naar de database schrijven}';

    protected $description = 'Leest de BIO-overheidsmaatregelen opnieuw in';

    /**
     * Wat BIO2 v1.3 hoort te bevatten: 118 geldende verplichtingen plus 4
     * vervallen en 5 verplaatste nummers.
     */
    private const VERWACHT = 127;

    private const VERWACHT_GELDEND = 118;

    /** @var list<string> */
    private const VERPLICHT = ['nummer', 'annex_a_referentie', 'volgnummer', 'status'];

    public function handle(): int
    {
        if (! Normprofiel::heeft('overheidsmaatregelen')) {
            $this->error('Dit profiel ('.Normprofiel::actief().') kent geen overheidsmaatregelen. '
                .'Ze horen bij de BIO; zie de kennisbank.');

            return self::FAILURE;
        }

        $pad = OverheidsmaatregelSeeder::bronpad();
        $tekstpad = OverheidsmaatregelSeeder::tekstpad();

        $this->line('Profiel      : '.Normprofiel::label('naam').' ('.Normprofiel::actief().')');
        $this->line('Bronbestand  : '.$pad);
        $this->line('Teksten      : '.(is_file($tekstpad) ? $tekstpad : 'niet aanwezig (alleen structuur)'));

        $rijen = $this->gecontroleerd($pad);

        if ($rijen === null) {
            return self::FAILURE;
        }

        $this->info(count($rijen).' overheidsmaatregelen, bestand is in orde.');

        if ($this->option('controleer')) {
            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => OverheidsmaatregelSeeder::class, '--force' => true]);

        $this->rapporteer();

        return self::SUCCESS;
    }

    /**
     * Het bestand, of null met een melding als er iets mis mee is.
     *
     * @return list<array<string, mixed>>|null
     */
    private function gecontroleerd(string $pad): ?array
    {
        if (! is_file($pad)) {
            $this->error('Dit bestand ontbreekt. Het hoort meegeleverd te zijn; herstel het uit het '
                .'installatiepakket of met git. De BIO downloaden lost dit niet op — dat bestand '
                .'draagt alleen de structuur.');

            return null;
        }

        try {
            $data = json_decode(file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Het bestand is geen geldige JSON: '.$e->getMessage());

            return null;
        }

        if (! is_array($data['overheidsmaatregelen'] ?? null)) {
            $this->error("Het bestand heeft geen lijst 'overheidsmaatregelen'.");

            return null;
        }

        $rijen = $data['overheidsmaatregelen'];

        if (count($rijen) !== self::VERWACHT) {
            $this->error('Verwacht '.self::VERWACHT.' regels, het bestand heeft '.count($rijen)
                .'. Is dit een nieuwe BIO-uitgave? Regenereer het bestand met '
                .'scripts/genereer_overheidsmaatregelen_seed.py.');

            return null;
        }

        $geldend = count(array_filter($rijen, fn (array $r) => ($r['status'] ?? null) === 'geldend'));

        if ($geldend !== self::VERWACHT_GELDEND) {
            $this->error("Verwacht ".self::VERWACHT_GELDEND." geldende verplichtingen, geteld {$geldend}.");

            return null;
        }

        return $this->velden($rijen) ? $rijen : null;
    }

    /**
     * @param  list<array<string, mixed>>  $rijen
     */
    private function velden(array $rijen): bool
    {
        $gezien = [];
        $goed = true;

        foreach ($rijen as $index => $rij) {
            $waar = ($rij['nummer'] ?? '?')." (regel {$index})";

            foreach (self::VERPLICHT as $veld) {
                if (! isset($rij[$veld]) || $rij[$veld] === '') {
                    $this->error("{$waar}: veld '{$veld}' ontbreekt of is leeg.");
                    $goed = false;
                }
            }

            // Een verplaatst nummer zonder doel is onbruikbaar: dan weet een
            // auditor dat het nummer wég is maar niet waarheen, en dat is precies
            // de vraag die hij stelt.
            if (($rij['status'] ?? null) === 'verplaatst' && ($rij['verwezen_naar'] ?? null) === null) {
                $this->error("{$waar}: status 'verplaatst' zonder verwijzing naar het nieuwe nummer.");
                $goed = false;
            }

            if (isset($gezien[$rij['nummer'] ?? ''])) {
                $this->error("{$waar}: dit nummer staat er twee keer in.");
                $goed = false;
            }

            $gezien[$rij['nummer'] ?? ''] = true;
        }

        return $goed;
    }

    /** Wat er nu in de database staat — de enige reden waarom iemand dit draait. */
    private function rapporteer(): void
    {
        $totaal = Overheidsmaatregel::count();
        $geldend = Overheidsmaatregel::where('status', 'geldend')->count();
        $metTekst = Overheidsmaatregel::where('status', 'geldend')
            ->whereNotNull('tekst')
            ->where('tekst', '!=', Overheidsmaatregel::TEKST_NIET_MEEGELEVERD)
            ->count();
        $buitenCbw = Overheidsmaatregel::where('cbw_reikwijdte', false)->count();

        $this->newLine();
        $this->info("{$totaal} overheidsmaatregelen bijgewerkt, waarvan {$geldend} geldend.");
        $this->line("Met eigen normtekst: {$metTekst} van de {$geldend}.");

        if ($metTekst === 0) {
            $this->line('Nog geen tekst ingelezen. Dat mag — de SoA toont dan het nummer met een '
                .'verwijzing naar de verantwoording. Zie de kennisbank voor hoe u de BIO-teksten '
                .'in uw eigen installatie zet.');
        }

        $this->line("Buiten de Cbw-reikwijdte: {$buitenCbw} verplichting(en).");

        $beoordelingen = OverheidsmaatregelBeoordeling::count();
        $onbeoordeeld = OverheidsmaatregelBeoordeling::where('status', 'niet_beoordeeld')->count();

        $this->line("Beoordelingen: {$beoordelingen}, waarvan {$onbeoordeeld} nog niet beoordeeld.");
    }
}
