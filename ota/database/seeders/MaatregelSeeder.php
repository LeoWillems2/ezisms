<?php

namespace Database\Seeders;

use App\Models\Maatregel;
use App\Models\SoaRegel;
use App\Support\Normprofiel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * De beheersmaatregelen plus een lege SoA-regel per maatregel
 * (implementatie/04f-maatregelseeds-vereenvoudigen.md §3).
 *
 * Eén bestand per normprofiel, `data/maatregelen-<profiel>.json`, en elk bestand
 * is in zijn eentje compleet. De seeder kiest op het profiel en beslist verder
 * niets.
 *
 * De bestanden dragen referenties, thema's en titels — openbaar bekend — plus
 * `Maatregel::OMSCHRIJVING_NIET_MEEGELEVERD` als markering dat er nog geen
 * normtekst staat. Een CISO met de norm vervangt die markering per maatregel;
 * gedeeltelijk invullen mag, want de markering staat per regel en niet in het
 * bestand als geheel.
 *
 * **Wat hier bewust níét meer staat.** Tot 06-08-2026 smolt deze seeder vier
 * bestanden samen en moest hij daarom een `RuntimeException` gooien op
 * `MAATREGELEN_BRON=basis` in zorgmodus: de eigen ISO-omschrijvingen zijn
 * geschreven vanuit de ISO-maatregel, terwijl veertien daarvan onder NEN 7510
 * een zwaardere eis dragen (04e §1). Met één bestand per norm is die kruising
 * niet meer *afgevangen* maar **onmogelijk**. Bouw die bescherming dus niet
 * terug — dan is er weer iets om te beschermen.
 *
 * Idempotent: opnieuw draaien werkt de maatregelen bij zonder bestaande
 * SoA-beoordelingen, classificaties, koppelingen of bewijsstukken te raken.
 */
class MaatregelSeeder extends Seeder
{
    /** De velden die uit het bestand komen; de rest van de rij is van de organisatie. */
    private const VELDEN = ['thema', 'naam', 'omschrijving', 'zorgaanvulling'];

    public static function bestandsnaam(?string $profiel = null): string
    {
        return 'maatregelen-'.($profiel ?? Normprofiel::actief()).'.json';
    }

    /**
     * Het absolute pad van het maatregelbestand van een profiel.
     *
     * De map komt uit `config('norm.maatregelenmap')` en dat is er voor de
     * tests: die mogen nooit in `database/seeders/data/` schrijven, want daar
     * staat het bestand van de installatie zelf — met de overgetypte normtekst
     * erin. Zelfde constructie als `Maatregelkenmerken::bronpad()`.
     */
    public static function bronpad(?string $profiel = null): string
    {
        $map = config('norm.maatregelenmap') ?: database_path('seeders/data');

        return rtrim($map, '/').'/'.self::bestandsnaam($profiel);
    }

    public function run(): void
    {
        $pad = self::bronpad();

        if (! is_file($pad)) {
            // Installatiefout en geen licentiekwestie: dit bestand hoort
            // meegeleverd te zijn. De norm kopen lost het niet op.
            $this->command?->warn(basename($pad).' ontbreekt — geen maatregelen geseed.');

            return;
        }

        $data = json_decode(file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR);

        foreach ($data['maatregelen'] as $regel) {
            $maatregel = Maatregel::updateOrCreate(
                ['annex_a_referentie' => $regel['annex_a_referentie']],
                Arr::only($regel, self::VELDEN),
            );

            SoaRegel::firstOrCreate(['maatregel_id' => $maatregel->id]);
        }

        $this->command?->info(count($data['maatregelen']).' maatregelen geseed uit '.basename($pad));
    }
}
