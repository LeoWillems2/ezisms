<?php

namespace App\Console\Commands;

use App\Models\Maatregel;
use App\Support\Normprofiel;
use Database\Seeders\MaatregelSeeder;
use Illuminate\Console\Command;
use JsonException;

/**
 * Leest `database/seeders/data/maatregelen-<profiel>.json` opnieuw in
 * (implementatie/04f-maatregelseeds-vereenvoudigen.md §4).
 *
 * `php artisan db:seed --class=MaatregelSeeder` doet hetzelfde schrijfwerk. Het
 * verschil is dat dit commando controleert vóór het schrijft en rapporteert
 * erna, en dat is precies wat de situatie vraagt: sinds 04f bewerkt de CISO dat
 * bestand zelf, met de norm ernaast. Een typefout in de JSON moet dan een
 * leesbare melding opleveren en geen halve seed.
 *
 * Het leest niets uit `.env`. Op een uitgerolde installatie is de configuratie
 * gecached en slaat Laravel `.env` over — dat is waar `MAATREGELEN_BRON` op
 * stukliep. De bestandskeuze hangt aan het normprofiel uit de database, dus er
 * valt niets meer te lezen.
 */
class Maatregelen extends Command
{
    protected $signature = 'isms:maatregelen
        {--controleer : alleen controleren, niets naar de database schrijven}';

    protected $description = 'Leest het maatregelbestand van dit normprofiel opnieuw in';

    /** Hoeveel maatregelen elk profiel hoort te hebben. */
    private const AANTALLEN = ['iso27001' => 93, 'nen7510' => 101];

    /** @var list<string> */
    private const VERPLICHT = ['annex_a_referentie', 'thema', 'naam', 'omschrijving', 'zorgaanvulling'];

    public function handle(): int
    {
        $profiel = Normprofiel::actief();
        $pad = MaatregelSeeder::bronpad($profiel);

        $this->line('Profiel      : '.Normprofiel::label('naam')." ({$profiel})");
        $this->line('Bronbestand  : '.$pad);

        $maatregelen = $this->gecontroleerd($pad, $profiel);

        if ($maatregelen === null) {
            return self::FAILURE;
        }

        $this->info(count($maatregelen).' maatregelen, bestand is in orde.');

        if ($this->option('controleer')) {
            return self::SUCCESS;
        }

        $this->callSilent('db:seed', ['--class' => MaatregelSeeder::class, '--force' => true]);

        $this->rapporteer($profiel);

        return self::SUCCESS;
    }

    /**
     * Het bestand, of null met een melding als er iets mis mee is.
     *
     * Alles wordt gecontroleerd vóórdat er iets wordt geschreven. Halverwege
     * afbreken zou een database achterlaten waarin de eerste veertig maatregelen
     * nieuw zijn en de rest oud, en dat is een lastiger probleem dan een bestand
     * dat geweigerd wordt.
     *
     * @return list<array<string, string>>|null
     */
    private function gecontroleerd(string $pad, string $profiel): ?array
    {
        if (! is_file($pad)) {
            $this->error('Dit bestand ontbreekt. Het hoort meegeleverd te zijn; herstel het uit het '
                .'installatiepakket of met git. De norm kopen lost dit niet op.');

            return null;
        }

        try {
            $data = json_decode(file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Het bestand is geen geldige JSON: '.$e->getMessage());
            $this->line('Veelgemaakte fouten: een komma te veel achter de laatste regel, een ontbrekend '
                .'aanhalingsteken, of een aanhalingsteken midden in een tekst dat niet is voorafgegaan '
                .'door een backslash.');

            return null;
        }

        if (! is_array($data['maatregelen'] ?? null)) {
            $this->error("Het bestand heeft geen lijst 'maatregelen'. Is het _over-kopblok per ongeluk "
                .'over de rest heen geschreven?');

            return null;
        }

        $maatregelen = $data['maatregelen'];
        $verwacht = self::AANTALLEN[$profiel] ?? null;

        if ($verwacht !== null && count($maatregelen) !== $verwacht) {
            $this->error("Dit profiel verwacht {$verwacht} maatregelen, het bestand heeft "
                .count($maatregelen).'. Er is er een verdwenen of bijgekomen.');

            return null;
        }

        return $this->velden($maatregelen) ? $maatregelen : null;
    }

    /**
     * @param  list<array<string, string>>  $maatregelen
     */
    private function velden(array $maatregelen): bool
    {
        $gezien = [];
        $goed = true;

        foreach ($maatregelen as $nummer => $regel) {
            $waar = 'A.'.($regel['annex_a_referentie'] ?? '?')." (regel {$nummer})";

            foreach (self::VERPLICHT as $veld) {
                if (! is_string($regel[$veld] ?? null) || $regel[$veld] === '') {
                    $this->error("{$waar}: veld '{$veld}' ontbreekt of is leeg.");
                    $goed = false;
                }
            }

            $referentie = $regel['annex_a_referentie'] ?? null;

            if ($referentie !== null && isset($gezien[$referentie])) {
                $this->error("{$waar}: deze referentie staat er twee keer in.");
                $goed = false;
            }

            $gezien[$referentie] = true;
        }

        return $goed;
    }

    /**
     * Wat er nu in de database staat. Zonder dit ziet de CISO niet of het
     * overtypwerk is aangekomen — en dat is de enige reden waarom hij dit
     * commando draait.
     */
    private function rapporteer(string $profiel): void
    {
        $totaal = Maatregel::count();
        $zonder = Maatregel::where('omschrijving', Maatregel::OMSCHRIJVING_NIET_MEEGELEVERD)->count();
        $eigen = $totaal - $zonder;

        $this->newLine();
        $this->info("{$totaal} maatregelen bijgewerkt: {$eigen} met een eigen normtekst, "
            ."{$zonder} met de meegeleverde mededeling.");

        if ($eigen === 0) {
            $this->line('Nog geen enkele normtekst ingevoerd. Dat mag — de SoA toont dan de officiële '
                .'titel met een verwijzing naar de verantwoording.');
        }

        if (Normprofiel::heeft('zorgaanvulling')) {
            $met = Maatregel::where('zorgaanvulling', '!=', Maatregel::ZORGAANVULLING_GEEN)->count();
            $this->line("Zorgspecifieke beheersmaatregelen: {$met} van de {$totaal} maatregelen hebben er een.");
        }
    }
}
