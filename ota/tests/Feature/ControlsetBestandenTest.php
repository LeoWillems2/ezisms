<?php

namespace Tests\Feature;

use App\Models\Maatregel;
use Tests\TestCase;

/**
 * De twee controlsets als bestand (implementatie/04f §9).
 *
 * Er is één bestand per normprofiel, en de 93 gedeelde maatregelen staan dus in
 * allebei — bewust aanvaarde dubbele boekhouding (04f §1.5). "Statisch" is
 * echter een aanname die alleen waar blijft zolang niemand er iets in wijzigt;
 * deze tests maken er een controle van.
 *
 * Sinds 11-08-2026 is dit de énige automatische bewaking die vóór een commit
 * kan aanslaan: de pre-commit-hook is verwijderd, en de controle in
 * builddistr.sh kijkt pas bij het uitleveren. Vóór dit plan stond
 * er `"Koop de norm"` in het naamveld en was elke andere waarde verdacht; nu
 * staan er legitiem echte titels in en is elke waarde syntactisch geldig. Wat
 * overblijft is vergelijken: de titels tussen de twee bestanden, en de velden
 * waar wél een vaste waarde in hoort.
 *
 * Geen database nodig — dit gaat over de bestanden in versiebeheer.
 */
class ControlsetBestandenTest extends TestCase
{
    /** De acht die NEN 7510-1:2024 toevoegt aan Bijlage A. */
    private const EXTRA = ['5.38', '5.39', '5.40', '5.41', '5.42', '5.43', '6.9', '8.35'];

    /** @return array<string, mixed> */
    private function bestand(string $profiel): array
    {
        $pad = database_path("seeders/data/maatregelen-{$profiel}.json");

        $this->assertFileExists($pad, 'Dit bestand hoort in versiebeheer te staan.');

        return json_decode(file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<int, array<string, string>> */
    private function maatregelen(string $profiel): array
    {
        return $this->bestand($profiel)['maatregelen'];
    }

    public function test_beide_bestanden_dragen_de_verwachte_referenties(): void
    {
        $iso = array_column($this->maatregelen('iso27001'), 'annex_a_referentie');
        $nen = array_column($this->maatregelen('nen7510'), 'annex_a_referentie');

        $this->assertCount(93, $iso);
        $this->assertCount(101, $nen);
        $this->assertSame($iso, array_unique($iso), 'Dubbele referentie in het ISO-bestand.');
        $this->assertSame($nen, array_unique($nen), 'Dubbele referentie in het NEN-bestand.');

        $this->assertSame(self::EXTRA, array_values(array_diff($nen, $iso)));
        $this->assertSame([], array_diff($iso, $nen), 'Het NEN-bestand hoort de 93 te bevatten.');
    }

    /**
     * De tegenhanger van de dubbele boekhouding (04f §1.5): wie een titel in één
     * bestand corrigeert en het andere vergeet, loopt hier tegenaan.
     */
    public function test_de_gedeelde_93_zijn_gelijk_in_beide_bestanden(): void
    {
        $sleutel = fn (array $rij) => [
            'thema' => $rij['thema'],
            'naam' => $rij['naam'],
        ];

        $iso = collect($this->maatregelen('iso27001'))->keyBy('annex_a_referentie')->map($sleutel);
        $nen = collect($this->maatregelen('nen7510'))->keyBy('annex_a_referentie')->map($sleutel);

        foreach ($iso as $referentie => $verwacht) {
            $this->assertSame(
                $verwacht,
                $nen[$referentie] ?? null,
                "A.{$referentie} verschilt tussen de twee controlsets."
            );
        }
    }

    /**
     * Beide bestanden staan in versiebeheer en mogen dus geen normtekst dragen.
     * Hier werd de pre-commit-hook door gedekt, en sinds die weg is, is dit de
     * bewaking zelf. Regenereert iemand met de echte norm erbij, dan valt dat
     * hier om — maar pas als de suite draait, niet bij `git commit`.
     *
     * Het is een gelijkheidstest en geen patroontest: sinds besluit 04f §1.1 is
     * er één toegestane waarde voor `omschrijving` en zijn er twee voor
     * `zorgaanvulling`. Strenger kan niet.
     */
    public function test_geen_bestand_draagt_normtekst(): void
    {
        foreach (['iso27001', 'nen7510'] as $profiel) {
            foreach ($this->maatregelen($profiel) as $regel) {
                $waar = "maatregelen-{$profiel}.json, A.{$regel['annex_a_referentie']}";

                $this->assertSame(
                    Maatregel::OMSCHRIJVING_NIET_MEEGELEVERD,
                    $regel['omschrijving'],
                    "{$waar} draagt een eigen omschrijving; dit bestand mag alleen de mededeling dragen."
                );

                $this->assertContains(
                    $regel['zorgaanvulling'],
                    [Maatregel::ZORGAANVULLING_GEEN, Maatregel::ZORGAANVULLING_NIET_MEEGELEVERD],
                    "{$waar} draagt een eigen zorgaanvulling."
                );
            }
        }
    }

    /**
     * In het ISO-profiel bestaat het zorgveld alleen om de twee bestanden
     * structureel gelijk te houden (04f §2). Staat er iets anders dan de
     * markering, dan is er een NEN-bestand overheen geschreven.
     */
    public function test_het_iso_bestand_draagt_geen_zorgaanvullingen(): void
    {
        foreach ($this->maatregelen('iso27001') as $regel) {
            $this->assertSame(Maatregel::ZORGAANVULLING_GEEN, $regel['zorgaanvulling']);
        }
    }

    /** Het kopblok is er voor de CISO die het bestand met een editor opent. */
    public function test_beide_bestanden_leggen_zichzelf_uit(): void
    {
        foreach (['iso27001', 'nen7510'] as $profiel) {
            $over = $this->bestand($profiel)['_over'] ?? [];

            $this->assertSame(
                ['wat', 'tekst', 'toestanden', 'commando'],
                array_keys($over),
                "Het _over-blok van maatregelen-{$profiel}.json is niet compleet."
            );
            $this->assertSame('php artisan isms:maatregelen', $over['commando']);
        }
    }
}
