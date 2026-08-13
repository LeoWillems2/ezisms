<?php

namespace Tests\Feature;

use App\Livewire\SoaOverzicht;
use App\Models\Gebruiker;
use App\Models\Maatregel;
use App\Support\Maatregelkenmerken;
use Database\Seeders\BlokSeeder;
use Database\Seeders\MaatregelKenmerkenSeeder;
use Database\Seeders\MaatregelSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * De zorgcontrolset (implementatie/04e-zorgcontrolset.md).
 *
 * De aanvullingsteksten zelf worden nooit meegeleverd; `maatregelen-nen7510.json`
 * draagt alleen wélke maatregelen er een hebben. Deze tests zetten de kolom
 * rechtstreeks waar ze over de drie toestanden gaan — de seed levert er nooit
 * meer dan twee.
 *
 * Elke test zet zijn eigen profiel, ook de ISO-kant: zie de klassenkop van
 * NormprofielTest voor waarom leunen op `phpunit.xml` hier niet werkt.
 */
#[Group('nen7510')]
class ZorgcontrolsetTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    private function zorgmodus(): void
    {
        config()->set('norm.actief', 'nen7510');
    }

    private function isomodus(): void
    {
        config()->set('norm.actief', 'iso27001');
    }

    public function test_iso_modus_levert_93_maatregelen(): void
    {
        $this->isomodus();
        $this->seed(MaatregelSeeder::class);

        $this->assertSame(93, Maatregel::count());
        $this->assertNull(Maatregel::where('annex_a_referentie', '5.43')->first());
        $this->assertSame(
            'Beleidsregels voor informatiebeveiliging',
            Maatregel::where('annex_a_referentie', '5.1')->sole()->naam,
        );
    }

    public function test_zorgmodus_levert_101_maatregelen(): void
    {
        $this->zorgmodus();
        $this->seed(MaatregelSeeder::class);

        $this->assertSame(101, Maatregel::count());

        $nieuw = Maatregel::where('annex_a_referentie', '5.43')->sole();
        $this->assertSame('Incidenten extern melden', $nieuw->naam);
        $this->assertSame('organisatorisch', $nieuw->thema);

        // Elke maatregel krijgt een SoA-regel, ook de acht nieuwe.
        $this->assertNotNull($nieuw->soaRegel);
        $this->assertSame('technologisch', Maatregel::where('annex_a_referentie', '8.35')->sole()->thema);
    }

    /**
     * De kerntest van dit plan, en sinds 04f geldt hij in beide profielen: het
     * ISMS levert nergens een eigen maatregeltekst mee. De reden verschoof
     * daarbij van licentie naar auditbaarheid — een eigen omschrijving is een
     * interpretatie van de norm, op de plek waar de auditor de toepasselijkheid
     * beoordeelt. Voor de zorgkant was het bovendien een onderschatting: 14 van
     * de 93 dragen onder NEN 7510 een zwaardere eis.
     */
    public function test_zorgmodus_levert_geen_enkele_maatregeltekst_maar_wel_titels(): void
    {
        $this->zorgmodus();
        $this->seed(MaatregelSeeder::class);

        $this->assertSame(101, Maatregel::where('omschrijving', Maatregel::OMSCHRIJVING_NIET_MEEGELEVERD)->count());
        $this->assertSame(0, Maatregel::whereNot('omschrijving', Maatregel::OMSCHRIJVING_NIET_MEEGELEVERD)->count());
        // Titels moeten er wél zijn: 101 naamloze regels maken de SoA onbruikbaar.
        $this->assertSame(0, Maatregel::whereNull('naam')->count());
        $this->assertSame('Beleidsregels voor informatiebeveiliging', Maatregel::where('annex_a_referentie', '5.1')->sole()->naam);
    }

    /**
     * Drie toestanden: `null` = deze rij is niet door de seeder aangeraakt,
     * DO NOT TOUCH = ingelezen en deze heeft er geen, tekst = de aanvulling.
     */
    public function test_de_drie_toestanden_van_de_zorgaanvulling(): void
    {
        $this->zorgmodus();
        $maatregel = Maatregel::factory()->create(['zorgaanvulling' => null]);

        $this->assertTrue($maatregel->toontZorgaanvulling());
        $this->assertSame(Maatregel::ZORGAANVULLING_NIET_INGELEZEN, $maatregel->zorgaanvullingTekst());

        $maatregel->update(['zorgaanvulling' => Maatregel::ZORGAANVULLING_GEEN]);
        $this->assertFalse($maatregel->fresh()->toontZorgaanvulling());

        $maatregel->update(['zorgaanvulling' => 'Voor PGI geldt aanvullend dat…']);
        $this->assertTrue($maatregel->fresh()->toontZorgaanvulling());
        $this->assertSame('Voor PGI geldt aanvullend dat…', $maatregel->fresh()->zorgaanvullingTekst());
    }

    public function test_het_blok_blijft_weg_in_iso_modus(): void
    {
        $this->isomodus();
        $maatregel = Maatregel::factory()->create(['zorgaanvulling' => 'Zorgtekst']);

        $this->assertFalse($maatregel->toontZorgaanvulling());
    }

    /**
     * De waarde wordt hier expliciet gezet en niet uit de seed gehaald. Sinds
     * 05-08-2026 staat `maatregelen-nen7510.json` wél in versiebeheer, maar het
     * levert nooit alle drie de toestanden: `null` ontstaat juist als dat bestand
     * er níét is, en die stand moet ook getest worden.
     */
    public function test_de_soa_toont_het_zorgaanvullingsblok(): void
    {
        $this->zorgmodus();
        $this->seed(MaatregelSeeder::class);

        $maatregel = Maatregel::where('annex_a_referentie', '5.43')->sole();
        $maatregel->update(['zorgaanvulling' => null]);

        $component = Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $maatregel->soaRegel->id)
            ->assertSee('Zorgspecifieke aanvulling (NEN 7510)')
            ->assertSee(Maatregel::ZORGAANVULLING_NIET_INGELEZEN);

        $maatregel->update(['zorgaanvulling' => 'Aanvullende zorgeis uit de norm.']);
        $component->call('bewerk', $maatregel->soaRegel->id)
            ->assertSee('Aanvullende zorgeis uit de norm.')
            ->assertDontSee(Maatregel::ZORGAANVULLING_NIET_INGELEZEN);

        // Ingelezen én geen aanvulling: geen blok, ook geen lege kop — en de
        // markering zelf komt nergens op het scherm.
        $maatregel->update(['zorgaanvulling' => Maatregel::ZORGAANVULLING_GEEN]);
        $component->call('bewerk', $maatregel->soaRegel->id)
            ->assertDontSee('Zorgspecifieke aanvulling')
            ->assertDontSee(Maatregel::ZORGAANVULLING_GEEN);
    }

    /**
     * De acht nieuwe maatregelen horen badges te hebben zoals de 93 andere; een
     * halve SoA is erger dan geen.
     */
    public function test_de_acht_zorgmaatregelen_krijgen_kenmerken(): void
    {
        $this->zorgmodus();
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);

        $this->assertNotEmpty(Maatregel::where('annex_a_referentie', '6.9')->sole()->kenmerken);
        $this->assertSame(0, Maatregel::whereNull('kenmerken')->count());
    }

    /** In ISO-modus bestaan die acht niet; de kenmerkenseeder mag daar niet op stuk lopen. */
    public function test_kenmerken_van_de_acht_worden_overgeslagen_in_iso_modus(): void
    {
        $this->isomodus();
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);

        $this->assertSame(93, Maatregel::count());
        $this->assertSame(0, Maatregel::whereNull('kenmerken')->count());
    }

    /**
     * Een maatregel zonder omschrijving hoort te zeggen dát er geen is. In
     * zorgmodus geldt dat voor alle 101, en bij de maatregelen zonder
     * zorgaanvulling houdt de modal anders alleen een titel over — dat ziet er
     * kapot uit in plaats van principieel.
     */
    public function test_de_soa_zegt_dat_er_geen_maatregeltekst_is(): void
    {
        $this->zorgmodus();
        $this->seed(MaatregelSeeder::class);

        // 5.43 is een van de acht. De aanvulling op de markering zetten laat
        // alleen deze zin over.
        $maatregel = Maatregel::where('annex_a_referentie', '5.43')->sole();
        $maatregel->update(['zorgaanvulling' => Maatregel::ZORGAANVULLING_GEEN]);

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $maatregel->soaRegel->id)
            ->assertSee(Maatregel::GEEN_OMSCHRIJVING_AANHEF)
            ->assertSee(Maatregel::DISCLAIMER_LABEL);
    }

    /**
     * Voert de organisatie de normtekst zelf in, dan verdwijnt de zin — ook in
     * zorgmodus. Het ISMS levert geen tekst mee; wat de CISO invoert is van hem.
     */
    public function test_de_zin_verdwijnt_zodra_de_normtekst_is_ingevoerd(): void
    {
        $this->zorgmodus();
        $this->seed(MaatregelSeeder::class);

        $maatregel = Maatregel::where('annex_a_referentie', '5.1')->sole();
        $this->assertFalse($maatregel->toontOmschrijving(), 'De seed hoort hier geen tekst te leveren.');

        $maatregel->update(['omschrijving' => 'De letterlijke normtekst bij 5.1.']);

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $maatregel->soaRegel->id)
            ->assertSee('De letterlijke normtekst bij 5.1.')
            ->assertDontSee(Maatregel::GEEN_OMSCHRIJVING_AANHEF);
    }

    // --- Geen normtekst in het seedbestand (besluit 05-08-2026) ------------

    /**
     * Het NEN-bestand draagt alleen de driedeling, geen aanvullingsteksten —
     * wélke maatregelen een zorgspecifieke beheersmaatregel hebben is openbaar
     * bekend, de tekst ervan niet. Dat maakt dit een echte repo-bewaking:
     * regenereert iemand met een oudere generator, dan komt de normtekst hier
     * binnen — en sinds de pre-commit-hook weg is, is dit de plek waar dat nog
     * opvalt vóórdat er uitgeleverd wordt.
     *
     * De tegenhanger voor beide bestanden en beide velden staat in
     * ControlsetBestandenTest; hier blijft de zorgkant, met het aantal erbij.
     */
    public function test_het_zorgbestand_draagt_geen_normtekst(): void
    {
        $pad = database_path('seeders/data/maatregelen-nen7510.json');

        $this->assertFileExists($pad, 'Dit bestand hoort in versiebeheer te staan.');

        $data = json_decode(file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(101, $data['maatregelen']);

        foreach ($data['maatregelen'] as $regel) {
            $this->assertContains(
                $regel['zorgaanvulling'],
                [Maatregel::ZORGAANVULLING_GEEN, Maatregel::ZORGAANVULLING_NIET_MEEGELEVERD],
                "A.{$regel['annex_a_referentie']} draagt eigen tekst. Draai "
                .'scripts/genereer_maatregelen_seed.py --profiel=nen7510 opnieuw.'
            );
        }
    }

    /** De zin staat in twee talen; ze mogen niet uiteenlopen. */
    public function test_de_generator_schrijft_dezelfde_zin(): void
    {
        $script = base_path('../scripts/genereer_maatregelen_seed.py');

        if (! is_file($script)) {
            $this->markTestSkipped('Generator niet op de verwachte plek.');
        }

        $bron = file_get_contents($script);

        $this->assertStringContainsString('"'.Maatregel::ZORGAANVULLING_NIET_MEEGELEVERD.'"', $bron);
        $this->assertStringContainsString('"'.Maatregel::ZORGAANVULLING_GEEN.'"', $bron);
    }

    // --- Het zorgvocabulaire bij `eigenschappen` (00j §2) -------------------

    public function test_eigenschappen_krijgt_er_in_zorgmodus_drie_waarden_bij(): void
    {
        $this->isomodus();
        $this->assertSame(
            ['Vertrouwelijkheid', 'Integriteit', 'Beschikbaarheid'],
            Maatregelkenmerken::waarden('eigenschappen')
        );

        $this->zorgmodus();

        $this->assertSame(
            ['Vertrouwelijkheid', 'Integriteit', 'Beschikbaarheid',
                'Authenticiteit', 'Onweerlegbaarheid', 'Controleerbaarheid'],
            Maatregelkenmerken::waarden('eigenschappen')
        );
    }

    /**
     * De herkomst is de verantwoording van het vocabulaire en staat in de UI en
     * in de export. Drie waarden erbij zonder te zeggen waar ze vandaan komen is
     * precies wat plan 04d een "plausibele invulling" noemt.
     */
    public function test_de_extra_waarden_brengen_hun_herkomst_mee(): void
    {
        $this->isomodus();
        $this->assertStringNotContainsString(
            'NEN 7510',
            Maatregelkenmerken::dimensies()['eigenschappen']['herkomst']
        );

        $this->zorgmodus();

        $herkomst = Maatregelkenmerken::dimensies()['eigenschappen']['herkomst'];
        $this->assertStringContainsString('CIA-driehoek', $herkomst);
        $this->assertStringContainsString('ISO 27799 via NEN 7510', $herkomst);
    }

    /**
     * De tegenhanger van RisicoSoaTest::test_kenmerken_bevatten_uitsluitend_geldige_vocabulaire,
     * die in ISO-modus draait en de acht dus nooit ziet. Zonder deze test kan een
     * zorgwaarde in het distribueerbare bestand verkeerd gespeld staan zonder dat
     * iets faalt — de badge blijft dan gewoon weg op het SoA-scherm.
     */
    public function test_kenmerken_blijven_in_zorgmodus_binnen_het_vocabulaire(): void
    {
        $this->zorgmodus();
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);

        foreach (Maatregel::all() as $maatregel) {
            foreach (array_keys(Maatregelkenmerken::dimensies()) as $dimensie) {
                $onbekend = array_diff(
                    $maatregel->kenmerken[$dimensie] ?? [],
                    Maatregelkenmerken::waarden($dimensie)
                );

                $this->assertEmpty(
                    $onbekend,
                    "A.{$maatregel->annex_a_referentie} heeft ongeldige {$dimensie}: ".implode(', ', $onbekend)
                );
            }
        }

        $this->assertContains(
            'Authenticiteit',
            Maatregel::where('annex_a_referentie', '5.39')->sole()->kenmerken['eigenschappen']
        );
    }

    /**
     * De zorgwaarden zijn uitsluitend toegekend aan de acht, en die bestaan in
     * ISO-modus niet. Zou er één bij de 93 staan, dan viel de badge daar buiten
     * het vocabulaire — zichtbaar noch geldig.
     */
    public function test_iso_modus_kent_nergens_een_zorgwaarde_toe(): void
    {
        $this->isomodus();
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);

        $this->zorgmodus();
        $zorgwaarden = array_diff(
            Maatregelkenmerken::waarden('eigenschappen'),
            ['Vertrouwelijkheid', 'Integriteit', 'Beschikbaarheid']
        );
        config()->set('norm.actief', 'iso27001');

        foreach (Maatregel::all() as $maatregel) {
            $this->assertEmpty(
                array_intersect($maatregel->kenmerken['eigenschappen'] ?? [], $zorgwaarden),
                "A.{$maatregel->annex_a_referentie} draagt een zorgwaarde in ISO-modus."
            );
        }
    }

    /**
     * De seeder verwijdert nooit iets. Aan een maatregel kunnen SoA-beoordelingen
     * en bewijsstukken hangen, en dat opruimen is geen beslissing voor een seeder.
     *
     * De waarschuwing die hier tot 06-08-2026 bij hoorde is vervallen met de
     * gedeelde bestanden: het normprofiel ligt vast bij de installatie en van
     * norm wisselen betekent de database opnieuw opbouwen, dus deze stand kan
     * alleen nog met de hand ontstaan.
     */
    public function test_de_seeder_verwijdert_achtergebleven_zorgmaatregelen_niet(): void
    {
        $this->zorgmodus();
        $this->seed(MaatregelSeeder::class);
        $this->assertSame(101, Maatregel::count());

        config()->set('norm.actief', 'iso27001');
        $this->artisan('db:seed', ['--class' => MaatregelSeeder::class])->assertSuccessful();

        $this->assertSame(101, Maatregel::count());
    }
}
