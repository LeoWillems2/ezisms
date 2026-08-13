<?php

namespace Tests\Feature;

use App\Livewire\RisicoCriteria;
use App\Livewire\RisicoDetail;
use App\Models\Gebruiker;
use App\Models\Risico;
use App\Models\RisicocriteriaVersie;
use App\Support\Beoordelingsschaal;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

/**
 * De beoordelingsschaal van kans en impact (implementatie/00j §1).
 *
 * Sinds 04g komt de schaal uit de actieve risicocriteriaversie en niet meer uit
 * `config/beoordelingsschaal.php`. De teksten zijn niet veranderd — de config is
 * nu de seedbron — dus de inhoudelijke assertions hieronder gelden onverkort;
 * wat verschoof is wáár ze vandaan komen en op welk moment het normprofiel er
 * nog toe doet. Dat is bij het seeden, niet meer tijdens het draaien.
 *
 * Elke test zet zijn eigen profiel; zie de klassenkop van NormprofielTest.
 * Anders dan de drie andere profielbestanden draagt dít bestand de groep
 * `nen7510` per méthode: de schaal zelf is norm-onafhankelijk en de ISO-kant
 * ervan hoort gewoon in de risico-suite thuis. Alleen wat over het
 * profielverschil gaat, hoort bij de delta.
 */
class BeoordelingsschaalTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    /**
     * Legt de criteria opnieuw aan voor één profiel.
     *
     * Het profiel doet er alleen tijdens het seeden toe: daarna is de schaal
     * data van déze installatie. Een test die twee profielen wil vergelijken
     * moet dus opnieuw seeden en niet alleen een configsleutel omzetten.
     */
    private function schaalVoor(string $profiel): void
    {
        config()->set('norm.actief', $profiel);

        RisicocriteriaVersie::query()->delete();
        RisicocriteriaVersie::vergeet();

        $this->seed(RisicocriteriaSeeder::class);
    }

    private function zorgmodus(): void
    {
        $this->schaalVoor('nen7510');
    }

    private function isomodus(): void
    {
        $this->schaalVoor('iso27001');
    }

    // --- De schaal zelf ----------------------------------------------------

    /**
     * De matrix, de drempels en de formule lopen alle drie tot 5. Ontbreekt er
     * een niveau, dan verdwijnt dat uit de keuzelijst zonder dat iets faalt, en
     * scoort niemand het nog.
     */
    #[Group('nen7510')]
    public function test_beide_assen_dekken_in_beide_profielen_vijf_niveaus(): void
    {
        foreach (['iso27001', 'nen7510'] as $profiel) {
            $this->schaalVoor($profiel);

            foreach (['kans', 'impact'] as $as) {
                $schaal = Beoordelingsschaal::as($as);

                $this->assertNotSame('', trim($schaal['label']));
                $this->assertNotSame('', trim($schaal['leidraad']), "{$as} in {$profiel} zonder leidraad");
                $this->assertSame([1, 2, 3, 4, 5], array_keys($schaal['niveaus']));

                foreach ($schaal['niveaus'] as $niveau => $definitie) {
                    $this->assertNotSame('', trim($definitie['naam']), "{$as} {$niveau} in {$profiel}");
                    $this->assertNotSame('', trim($definitie['omschrijving']), "{$as} {$niveau} in {$profiel}");
                    // Leeg uitgeleverd, en dat is de bedoeling: het ISMS levert
                    // geen omzetpercentage mee (04g §2.3).
                    $this->assertNull($definitie['kwantitatieve_band']);
                }
            }
        }
    }

    /**
     * Het besluit uit nen7510-opzet.md §4.4: de kansschaal is een frequentie en
     * dus norm-onafhankelijk, de impactschaal is waar de norm iets toevoegt.
     */
    #[Group('nen7510')]
    public function test_de_impactschaal_verschilt_per_profiel_en_de_kansschaal_niet(): void
    {
        $this->isomodus();
        $kansIso = Beoordelingsschaal::niveaus('kans');
        $impactIso = Beoordelingsschaal::niveaus('impact');

        $this->zorgmodus();

        $this->assertSame($kansIso, Beoordelingsschaal::niveaus('kans'));
        $this->assertNotSame($impactIso, Beoordelingsschaal::niveaus('impact'));
    }

    /**
     * De kern van §4.4: patiëntveiligheid krijgt geen tweede as maar landt in wat
     * de niveaus betekenen, met de opdracht de zwaarste van de twee te scoren.
     */
    #[Group('nen7510')]
    public function test_de_zorgimpactschaal_weegt_de_cliënt_mee(): void
    {
        $this->zorgmodus();
        $schaal = Beoordelingsschaal::as('impact');

        $this->assertStringContainsString('zwaarste van de twee', $schaal['leidraad']);
        $this->assertStringContainsString('cliënt', $schaal['niveaus'][5]['omschrijving']);
        $this->assertStringContainsString('gezondheid', $schaal['niveaus'][5]['omschrijving']);

        // ISO noemt de cliënt nergens: die schaal weegt bedrijfsvoering,
        // betrokkenen, wettelijke plicht en financiën.
        $this->isomodus();
        $this->assertStringNotContainsString('cliënt', Beoordelingsschaal::niveaus('impact')[5]['omschrijving']);
    }

    public function test_opties_zetten_het_cijfer_voorop(): void
    {
        $this->isomodus();
        $this->assertSame('3 — Middelmatig', Beoordelingsschaal::opties('kans')[3]);
        $this->assertCount(5, Beoordelingsschaal::opties('impact'));
        $this->assertSame('Zeer groot', Beoordelingsschaal::naam('kans', 5));
        $this->assertNull(Beoordelingsschaal::naam('kans', null));
    }

    /**
     * Wat de organisatie vaststelt is wat er op het scherm staat — niet wat er
     * in de config stond. Dat is het hele punt van 04g.
     */
    public function test_de_schaal_komt_uit_de_actieve_versie_en_niet_uit_de_config(): void
    {
        $this->isomodus();

        RisicocriteriaVersie::actief()->niveaus()
            ->where('as', 'impact')->where('niveau', 4)
            ->update(['naam' => 'Ernstig', 'kwantitatieve_band' => '1 tot 5% van de jaaromzet']);
        RisicocriteriaVersie::vergeet();

        $this->assertSame('Ernstig', Beoordelingsschaal::naam('impact', 4));
        $this->assertSame(
            '1 tot 5% van de jaaromzet',
            Beoordelingsschaal::niveaus('impact')[4]['kwantitatieve_band'],
        );
        // De config is onaangeroerd gebleven; die is alleen nog seedbron.
        $this->assertSame('Groot', config('beoordelingsschaal.impact.profielen.iso27001.niveaus.4.naam'));
    }

    // --- Wat er hard moet falen (§1.2) -------------------------------------

    public function test_een_onbekende_as_gooit(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Onbekende beoordelingsas 'ernst'");

        Beoordelingsschaal::as('ernst');
    }

    /**
     * Zonder vastgesteld kader is er geen schaal. Stil een lege keuzelijst
     * tonen zou betekenen dat niemand meer een risico kan scoren zonder dat er
     * iets faalt.
     */
    public function test_zonder_actieve_criteria_gooit_de_schaal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('geen actieve risicocriteria');

        Beoordelingsschaal::as('kans');
    }

    /**
     * Het profiel doet er alleen bij het seeden toe. Stil terugvallen op ISO zou
     * een installatie laten scoren op een schaal die haar eigen norm niet kent.
     */
    #[Group('nen7510')]
    public function test_een_profiel_zonder_impactschaal_gooit_bij_het_seeden(): void
    {
        config()->set('norm.profielen.iso9001', ['labels' => [], 'capaciteiten' => []]);
        config()->set('norm.actief', 'iso9001');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("geen definitie voor normprofiel 'iso9001'");

        $this->seed(RisicocriteriaSeeder::class);
    }

    public function test_een_gat_in_de_niveaus_gooit(): void
    {
        $this->isomodus();

        RisicocriteriaVersie::actief()->niveaus()->where('as', 'kans')->where('niveau', 5)->delete();
        RisicocriteriaVersie::vergeet();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dekt niet precies de niveaus 1, 2, 3, 4, 5');

        Beoordelingsschaal::as('kans');
    }

    // --- Op het scherm (§1.3) ----------------------------------------------

    public function test_de_criteriapagina_toont_beide_schalen(): void
    {
        $this->isomodus();
        Livewire::actingAs($this->ciso)
            ->test(RisicoCriteria::class)
            ->assertSee('Beoordelingsschaal — Kans')
            ->assertSee('Beoordelingsschaal — Impact')
            ->assertSee('3 — Middelmatig')
            ->assertSee('Zeer groot')
            // De kwantitatieve band bestaat en is zichtbaar leeg: dat is hoe de
            // organisatie ziet dat dit veld er is zonder dat het verplicht wordt.
            ->assertSee('nog niet gekwantificeerd');
    }

    #[Group('nen7510')]
    public function test_de_criteriapagina_toont_in_zorgmodus_de_zorgschaal(): void
    {
        $this->zorgmodus();

        Livewire::actingAs($this->ciso)
            ->test(RisicoCriteria::class)
            ->assertSee('zwaarste van de twee')
            ->assertSee('Onomkeerbare schade aan de gezondheid van een cliënt', false);
    }

    public function test_de_keuzelijsten_op_het_risico_tonen_de_namen(): void
    {
        $this->isomodus();
        $risico = Risico::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(RisicoDetail::class, ['risico' => $risico])
            ->assertSee('1 — Zeer klein')
            ->assertSee('5 — Zeer groot')
            ->assertSee('Verwaarloosbaar');
    }

    // De export drukt de schaal af boven de risico's; die assertie staat in
    // ExporteerIsmsTest, want de suites zijn per bestand disjunct.
}
