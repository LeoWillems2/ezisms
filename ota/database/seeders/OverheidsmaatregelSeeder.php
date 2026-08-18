<?php

namespace Database\Seeders;

use App\Models\Maatregel;
use App\Models\Overheidsmaatregel;
use App\Models\OverheidsmaatregelBeoordeling;
use App\Models\SoaRegel;
use App\Support\Normprofiel;
use Illuminate\Database\Seeder;

/**
 * De BIO-overheidsmaatregelen plus een lege beoordeling per geldende verplichting
 * (deelproducten/04b-bio-overheidsmaatregelen.md §3.4).
 *
 * Doet niets buiten een installatie met de capaciteit `overheidsmaatregelen`. Dat
 * is een stille terugkeer en geen fout: `DatabaseSeeder` draait in élk profiel, en
 * een ISO-installatie hoort hier gewoon niets van te merken.
 *
 * **Het bronbestand draagt geen normtekst.** De BIO staat onder CC BY-NC-SA 4.0 en
 * of het Cyberbeveiligingsbesluit die beperking opheft is een open juridische vraag
 * (implementatie/00q §8). Wat in versiebeheer staat is de structuur: nummers, de
 * koppeling naar de beheersmaatregel, de status en de Cbw-reikwijdte. Een
 * installatie die de BIO zelf heeft gedownload, legt de teksten ernaast in
 * `overheidsmaatregel-teksten.json` — gitignored, en deze seeder leest hem als hij
 * er staat. Zelfde constructie als `maatregel-capaciteiten.json`.
 *
 * Idempotent, en dat is hier meer werk dan elders: bij een nieuwe BIO-uitgave
 * moeten bestaande beoordelingen de juiste kant op bewegen. Zie `run()`.
 */
class OverheidsmaatregelSeeder extends Seeder
{
    public static function bestandsnaam(): string
    {
        return 'overheidsmaatregelen-bio2.json';
    }

    /** Zelfde constructie als `MaatregelSeeder::bronpad()`: de map is instelbaar voor de tests. */
    public static function bronpad(): string
    {
        $map = config('norm.maatregelenmap') ?: database_path('seeders/data');

        return rtrim($map, '/').'/'.self::bestandsnaam();
    }

    /** Het lokale, niet-meegeleverde tekstbestand. */
    public static function tekstpad(): string
    {
        $map = config('norm.maatregelenmap') ?: database_path('seeders/data');

        return rtrim($map, '/').'/overheidsmaatregel-teksten.json';
    }

    public function run(): void
    {
        if (! Normprofiel::heeft('overheidsmaatregelen')) {
            return;
        }

        $pad = self::bronpad();

        if (! is_file($pad)) {
            // Installatiefout en geen licentiekwestie: de structuur hoort
            // meegeleverd te zijn. De BIO downloaden lost dit niet op.
            $this->command?->warn(basename($pad).' ontbreekt — geen overheidsmaatregelen geseed.');

            return;
        }

        $data = json_decode(file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR);
        $teksten = $this->teksten();

        // De referenties in één keer ophalen: 127 rijen die elk hun
        // beheersmaatregel opzoeken is 127 queries voor iets wat één query is.
        $maatregelen = Maatregel::pluck('id', 'annex_a_referentie');
        $soaRegels = SoaRegel::pluck('id', 'maatregel_id');

        $this->reikwijdte($data['buiten_cbw_reikwijdte'] ?? []);

        $verplaatsingen = [];
        $geschreven = 0;

        foreach ($data['overheidsmaatregelen'] as $regel) {
            $maatregelId = $maatregelen[$regel['annex_a_referentie']] ?? null;

            // Een verplichting zonder beheersmaatregel kan niet bestaan — de
            // nummering ís de verwijzing. Overslaan en niet een lege maatregel
            // aanmaken; de generator controleert de dekking al hard.
            if ($maatregelId === null) {
                $this->command?->warn("{$regel['nummer']}: beheersmaatregel "
                    ."{$regel['annex_a_referentie']} bestaat niet — overgeslagen.");

                continue;
            }

            $overheidsmaatregel = Overheidsmaatregel::updateOrCreate(
                ['nummer' => $regel['nummer']],
                [
                    'maatregel_id' => $maatregelId,
                    'volgnummer' => $regel['volgnummer'],
                    'status' => $regel['status'],
                    'verwezen_naar' => $regel['verwezen_naar'],
                    'cbw_reikwijdte' => $regel['cbw_reikwijdte'],
                    'tekst' => $teksten[$regel['nummer']] ?? $regel['tekst'],
                ],
            );

            $geschreven++;

            if ($overheidsmaatregel->status === 'verplaatst' && $overheidsmaatregel->verwezen_naar !== null) {
                $verplaatsingen[$overheidsmaatregel->nummer] = $overheidsmaatregel->verwezen_naar;
            }

            // Alleen geldende verplichtingen krijgen een beoordelingsrij. Een
            // vervallen nummer blijft zichtbaar als referentiedata, maar er valt
            // niets meer over vast te stellen.
            if ($overheidsmaatregel->isGeldend() && ($soaRegelId = $soaRegels[$maatregelId] ?? null) !== null) {
                OverheidsmaatregelBeoordeling::firstOrCreate([
                    'soa_regel_id' => $soaRegelId,
                    'overheidsmaatregel_id' => $overheidsmaatregel->id,
                ]);
            }
        }

        $verhuisd = $this->verhuisBeoordelingen($verplaatsingen);

        $this->command?->info("{$geschreven} overheidsmaatregelen geseed uit ".basename($pad)
            .($verhuisd > 0 ? ", {$verhuisd} beoordeling(en) meeverhuisd" : '')
            .($teksten === [] ? '' : ' (met lokale teksten)'));
    }

    /**
     * De teksten uit het lokale bestand, of leeg.
     *
     * @return array<string, string>
     */
    private function teksten(): array
    {
        $pad = self::tekstpad();

        if (! is_file($pad)) {
            return [];
        }

        $data = json_decode(file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR);

        return $data['teksten'] ?? [];
    }

    /**
     * De beheersmaatregelen die buiten de reikwijdte van de Cbw vallen.
     *
     * Op dit niveau en niet alleen per overheidsmaatregel: van de drie
     * beheersmaatregelen die de BIO grijs markeert, hebben er twee helemaal geen
     * overheidsmaatregel (5.32 en 5.34 dragen `Geen overheidsmaatregel`). Zonder
     * deze stap zou het ISMS juist daar zwijgen over de reikwijdte.
     *
     * Zet ook de andere kant terug op `true`. Verdwijnt een referentie bij een
     * volgende uitgave uit de lijst, dan hoort de vlag mee te bewegen — anders
     * blijft het ISMS beweren dat een maatregel buiten de wet valt terwijl de norm
     * dat niet meer zegt.
     *
     * @param  list<string>  $referenties
     */
    private function reikwijdte(array $referenties): void
    {
        Maatregel::whereIn('annex_a_referentie', $referenties)
            ->where('cbw_reikwijdte', true)
            ->update(['cbw_reikwijdte' => false]);

        Maatregel::whereNotIn('annex_a_referentie', $referenties)
            ->where('cbw_reikwijdte', false)
            ->update(['cbw_reikwijdte' => true]);
    }

    /**
     * Verhuist de beoordeling van een verplaatst nummer naar zijn opvolger.
     *
     * Bij de vijf bekende verplaatsingen in BIO2 v1.3 is de inhoud van de
     * verplichting gelijk gebleven — alleen het nummer veranderde, omdat de
     * bovenliggende beheersmaatregel wijzigde. De eerdere vaststelling geldt dan
     * nog, en die weggooien zou de CISO laten herbeoordelen wat hij al beoordeeld
     * heeft.
     *
     * Alleen als het doel nog onbeoordeeld is. Staat daar al iets, dan is dat een
     * latere uitspraak en die wint; de oude blijft als historie op het oude nummer
     * staan.
     *
     * @param  array<string, string>  $verplaatsingen  oud nummer => nieuw nummer
     */
    private function verhuisBeoordelingen(array $verplaatsingen): int
    {
        if ($verplaatsingen === []) {
            return 0;
        }

        $ids = Overheidsmaatregel::whereIn('nummer', array_merge(
            array_keys($verplaatsingen),
            array_values($verplaatsingen),
        ))->pluck('id', 'nummer');

        $verhuisd = 0;

        foreach ($verplaatsingen as $oud => $nieuw) {
            if (! isset($ids[$oud], $ids[$nieuw])) {
                continue;
            }

            $bron = OverheidsmaatregelBeoordeling::where('overheidsmaatregel_id', $ids[$oud])
                ->where('status', '!=', 'niet_beoordeeld')
                ->first();

            if ($bron === null) {
                continue;
            }

            $doel = OverheidsmaatregelBeoordeling::where('overheidsmaatregel_id', $ids[$nieuw])
                ->where('status', 'niet_beoordeeld')
                ->first();

            if ($doel === null) {
                continue;
            }

            $doel->update($bron->only([
                'status', 'motivatie', 'risicobehandeling_id',
                'laatst_beoordeeld_op', 'beoordeeld_door_id',
            ]));

            $verhuisd++;
        }

        return $verhuisd;
    }
}
