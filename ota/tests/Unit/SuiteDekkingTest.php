<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Bewaakt de disjuncte testsuite-indeling in phpunit.xml (blok token-optimalisatie):
 * elk tests/Feature-bestand moet in precies één <testsuite> staan. Zonder deze
 * bewaking zou een nieuw testbestand dat je vergeet in te delen stilletjes buiten
 * de kale `php artisan test`-run vallen, want die draait de domein-suites, niet
 * meer een tests/Feature-directory.
 *
 * Bewust een Unit-test (geen DB, geen app-bootstrap): het leest alleen bestanden.
 */
class SuiteDekkingTest extends TestCase
{
    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<string> basenames van de <file>-regels onder tests/Feature/ */
    private function ingedeeldeBestanden(): array
    {
        $xml = simplexml_load_file($this->projectRoot().'/phpunit.xml');
        $this->assertNotFalse($xml, 'phpunit.xml kon niet worden gelezen.');

        $bestanden = [];
        foreach ($xml->testsuites->testsuite as $suite) {
            foreach ($suite->file as $file) {
                $pad = trim((string) $file);
                if (str_starts_with($pad, 'tests/Feature/')) {
                    $bestanden[] = basename($pad);
                }
            }
        }

        return $bestanden;
    }

    public function test_elk_feature_testbestand_staat_in_een_suite(): void
    {
        $bestaand = array_map('basename', glob($this->projectRoot().'/tests/Feature/*.php'));
        $ingedeeld = $this->ingedeeldeBestanden();

        $nietIngedeeld = array_diff($bestaand, $ingedeeld);

        $this->assertSame([], array_values($nietIngedeeld),
            'Deze Feature-testbestanden staan in geen enkele phpunit.xml-suite en vallen dus buiten de volledige run: '
            .implode(', ', $nietIngedeeld));
    }

    public function test_geen_suite_verwijst_naar_een_verdwenen_bestand(): void
    {
        $bestaand = array_map('basename', glob($this->projectRoot().'/tests/Feature/*.php'));
        $ingedeeld = $this->ingedeeldeBestanden();

        $verdwenen = array_diff($ingedeeld, $bestaand);

        $this->assertSame([], array_values($verdwenen),
            'phpunit.xml verwijst naar Feature-bestanden die niet meer bestaan: '.implode(', ', $verdwenen));
    }

    public function test_geen_dubbel_ingedeeld_bestand(): void
    {
        $ingedeeld = $this->ingedeeldeBestanden();
        $dubbel = array_keys(array_filter(array_count_values($ingedeeld), fn ($n) => $n > 1));

        $this->assertSame([], $dubbel,
            'Deze bestanden staan in meer dan één suite (suites moeten disjunct zijn): '.implode(', ', $dubbel));
    }

    /**
     * De normprofiel-delta is een **groep** en geen suite (implementatie/00k §2).
     *
     * `nen7510-opzet.md` §4.8 vroeg om een suite `norm-nen7510`, maar de delta
     * ligt verspreid over vier domeinen (toegang, risico-soa, kennisbank, demo)
     * en een bestand mag maar in één suite staan — de test hierboven. Die tests
     * uit hun domeinsuite halen zou erger zijn: wie aan de kennisbank werkt zou
     * dan niet meer de test op het profielfilter in `Kennisartikelen` draaien.
     *
     * Groepen staan per constructie los van de suite-indeling:
     *
     *     php artisan test --group=nen7510
     *     php artisan test --group=bio2
     *
     * Deze bewaking is grof en dat is bewust: ze kijkt per bestand, niet per
     * methode. Wie in `BeoordelingsschaalTest` een zorgtest bijzet zonder
     * attribuut, wordt hier niet betrapt. Wat ze wél vangt is het geval dat in de
     * praktijk gebeurt: een nieuw testbestand over het normprofiel dat de groep
     * helemaal vergeet, en dan stilzwijgend buiten `--group=nen7510` valt.
     */
    public function test_elke_profieltest_draagt_de_groep_van_zijn_profiel(): void
    {
        // Sleutel = het profiel zoals een test het omzet, waarde = de groepsnaam.
        // Zet je hier een profiel bij, dan is de bewaking er meteen voor alle
        // bestaande bestanden.
        $groepen = ['nen7510' => 'nen7510', 'bio2' => 'bio2'];
        $zonderGroep = [];

        foreach (glob($this->projectRoot().'/tests/Feature/*.php') as $pad) {
            $bron = file_get_contents($pad);

            foreach ($groepen as $profiel => $groep) {
                // Het profiel omzetten is het kenmerk; alleen de naam noemen niet
                // — die staat ook in toelichtingen van tests die er niet over gaan.
                if (! str_contains($bron, "norm.actief', '{$profiel}'")) {
                    continue;
                }

                if (! str_contains($bron, "#[Group('{$groep}')]")) {
                    $zonderGroep[] = basename($pad).' ('.$groep.')';
                }
            }
        }

        $this->assertSame([], $zonderGroep,
            'Deze tests zetten een normprofiel om maar dragen de bijbehorende groep niet, '
            .'en vallen dus buiten `--group=<profiel>`: '.implode(', ', $zonderGroep));
    }
}
