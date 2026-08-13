<?php

namespace Tests\Feature;

use App\Livewire\Concerns\LevertSchermkopie;
use App\Models\Gebruiker;
use App\Models\SchermkopieRegistratie;
use App\Support\Pandoc;
use App\Support\Schermafbeelding;
use App\Support\Schermkopie;
use App\Support\SchermkopieNietBeschikbaar;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Het mechanisme uit implementatie/12h §12a, schermloos getoetst.
 *
 * De kolomcontrole per scherm (test 7 uit §11) hoort bij dat scherm en staat
 * daarom niet hier.
 *
 * De tests toetsen de **markdown**, niet de binary: dan draaien ze zonder dat
 * pandoc geïnstalleerd hoeft te zijn, net als bij `Documentpreview`.
 */
class SchermkopieTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create(['naam' => 'Dana Wolters']);
    }

    private function kopie(array $rijen, int $totaal, array $filters = []): Schermkopie
    {
        return new Schermkopie(
            scherm: 'Verklaring van Toepasselijkheid',
            kolommen: ['Referentie', 'Maatregel', 'Van toepassing'],
            rijen: $rijen,
            totaalRijen: $totaal,
            filters: $filters,
        );
    }

    public function test_de_kop_noemt_scherm_organisatie_en_samensteller(): void
    {
        $this->actingAs($this->ciso);
        config(['app.organisatie' => 'Fruit BV']);

        $markdown = $this->kopie([['A.5.1', 'Beleidsregels', 'Ja']], 1)->markdown();

        $this->assertStringContainsString('# Verklaring van Toepasselijkheid', $markdown);
        $this->assertStringContainsString('| Organisatie | Fruit BV |', $markdown);
        $this->assertStringContainsString('| Gemaakt door | Dana Wolters |', $markdown);

        // De inhoud zelf, als tabel.
        $this->assertStringContainsString('| Referentie | Maatregel | Van toepassing |', $markdown);
        $this->assertStringContainsString('| A.5.1 | Beleidsregels | Ja |', $markdown);
    }

    /**
     * De datum stond tot 13-08-2026 in de kop én sinds die dag in de voettekst
     * van elke pagina. Twee namen voor één moment — "Gemaakt op" en
     * "Printdatum" — is iets waar een auditor over struikelt, dus de kop laat
     * hem nu aan de voet over.
     */
    public function test_de_kop_draagt_geen_datum_meer(): void
    {
        $this->actingAs($this->ciso);

        $markdown = $this->kopie([['A.5.1', 'Beleidsregels', 'Ja']], 1)->markdown();

        $this->assertStringNotContainsString('Gemaakt op', $markdown);
        $this->assertStringNotContainsString(now()->format('d-m-Y'), $markdown);
    }

    /**
     * De test bij §4. Een document met 36 rijen dat leest als het volledige
     * register is het gevaarlijkst denkbare artefact in een auditdossier.
     */
    public function test_de_kop_noemt_het_filter_en_hoeveel_van_hoeveel(): void
    {
        $rijen = array_fill(0, 36, ['A.5.1', 'Beleidsregels', 'Ja']);

        $gefilterd = $this->kopie($rijen, 93, [
            'Thema' => 'organisatorisch',
            'Van toepassing' => 'ja',
        ])->markdown();

        $this->assertStringContainsString(
            '| Omvang | 36 van 93 regels — filter: thema organisatorisch, van toepassing ja. |',
            $gefilterd,
        );
    }

    /**
     * Zonder ingevulde organisatienaam blijft de regel weg. Er staat nooit de
     * naam van de software waar die van de organisatie hoort te staan.
     */
    public function test_een_lege_organisatienaam_levert_geen_regel(): void
    {
        config(['app.organisatie' => '']);

        $markdown = $this->kopie([['A.5.1', 'Beleidsregels', 'Ja']], 1)->markdown();

        $this->assertStringNotContainsString('| Organisatie |', $markdown);
        $this->assertStringNotContainsString(config('app.name'), $markdown);
    }

    public function test_zonder_filter_zegt_de_kop_dat_het_alles_is(): void
    {
        $volledig = $this->kopie([['A.5.1', 'Beleidsregels', 'Ja']], 1)->markdown();

        $this->assertStringContainsString('| Omvang | Alle 1 regels. |', $volledig);
    }

    /**
     * Zwijgen is geen optie: minder rijen dan het totaal moet zichtbaar zijn,
     * ook als er geen filterlabel bij hoort (bijvoorbeeld bij paginering).
     */
    public function test_minder_rijen_zonder_filterlabel_wordt_toch_gemeld(): void
    {
        $markdown = $this->kopie([['A.5.1', 'Beleidsregels', 'Ja']], 93)->markdown();

        $this->assertStringContainsString('| Omvang | 1 van 93 regels. |', $markdown);
    }

    /**
     * Niet elk scherm toont een register. De eenheid is per scherm te kiezen
     * zodat een matrix geen "alle 5 regels" hoeft te zeggen — maar de regel zelf
     * wordt er niet zachter van: het blijft getoond-van-totaal, mét de filters.
     */
    public function test_de_eenheid_in_de_omvangregel_is_per_scherm_te_kiezen(): void
    {
        $kopie = fn (array $filters) => new Schermkopie(
            scherm: 'Tolerantiematrix',
            kolommen: ['Impact', 'Kans 1'],
            rijen: [['5', '0'], ['4', '2'], ['3', '1']],
            totaalRijen: 5,
            filters: $filters,
            eenheid: 'impactniveaus',
        );

        $this->assertSame('3 van 5 impactniveaus.', $kopie([])->omvangregel());
        $this->assertSame(
            '3 van 5 impactniveaus — filter: kans 3.',
            $kopie(['Kans' => '3'])->omvangregel(),
        );
    }

    /**
     * De test bij §7a. Pandoc draait met `--sandbox` en komt dan niet bij het
     * bestandssysteem: een afbeelding met een pad levert een waarschuwing op
     * stderr op, exit 0, en een document zónder plaatje. Alleen een `data:`-URI
     * wordt ingesloten, en daarom neemt `Schermafbeelding` bytes en geen pad.
     */
    public function test_een_afbeelding_gaat_als_data_uri_mee_en_niet_als_pad(): void
    {
        $markdown = (new Schermkopie(
            scherm: 'Tolerantiematrix',
            kolommen: ['Impact'],
            rijen: [['5']],
            totaalRijen: 1,
            afbeelding: new Schermafbeelding(
                png: 'net alsof dit PNG-bytes zijn',
                // De blokhaken moeten eruit: die breken de afbeeldingssyntaxis.
                bijschrift: 'Tolerantiematrix [2026]',
                breedteCm: 12.0,
            ),
        ))->markdown();

        $this->assertStringContainsString(
            '![Tolerantiematrix 2026](data:image/png;base64,'
                .base64_encode('net alsof dit PNG-bytes zijn').'){ width=12cm }',
            $markdown,
        );
        $this->assertStringNotContainsString('.png)', $markdown);
    }

    /**
     * Een scherm zonder afbeelding — of een server zonder GD of lettertype —
     * levert een document dat verder gewoon compleet is. Geen leeg kader, geen
     * kapotte verwijzing.
     */
    public function test_zonder_afbeelding_staat_er_geen_afbeelding_in_de_markdown(): void
    {
        $this->assertStringNotContainsString(
            '![',
            $this->kopie([['A.5.1', 'Beleidsregels', 'Ja']], 1)->markdown(),
        );
    }

    public function test_een_pijp_in_een_celwaarde_breekt_de_tabel_niet(): void
    {
        $markdown = $this->kopie([['A.5.1', 'Beleid | procedures', 'Ja']], 1)->markdown();

        $rij = collect(explode("\n", $markdown))->first(fn (string $r) => str_contains($r, 'A.5.1'));

        // Drie kolommen = vier niet-ontsnapte pijpen; de ontsnapte telt niet mee.
        $this->assertSame(4, substr_count($rij, '|') - substr_count($rij, '\\|'));
        $this->assertStringContainsString('Beleid \\| procedures', $rij);
    }

    public function test_de_vastlegging_bevat_scherm_filters_aantallen_en_de_vlag(): void
    {
        $this->actingAs($this->ciso);

        $this->kopie(array_fill(0, 36, ['A.5.1', 'Beleidsregels', 'Ja']), 93, ['Thema' => 'organisatorisch'])
            ->legVast();

        $regel = SchermkopieRegistratie::firstOrFail();

        $this->assertSame('Verklaring van Toepasselijkheid', $regel->scherm);
        $this->assertSame(['Thema' => 'organisatorisch'], $regel->filters);
        $this->assertSame(36, $regel->aantal_rijen);
        $this->assertSame(93, $regel->totaal_rijen);
        $this->assertFalse($regel->met_persoonsgegevens);
        $this->assertSame($this->ciso->id, $regel->gebruiker_id);
        $this->assertFalse($regel->isVolledig());
    }

    public function test_een_vastlegging_is_niet_te_wijzigen_of_te_verwijderen(): void
    {
        $this->actingAs($this->ciso);
        $regel = $this->kopie([['A.5.1', 'Beleidsregels', 'Ja']], 1)->legVast();

        $this->expectException(RuntimeException::class);
        $regel->update(['aantal_rijen' => 0]);
    }

    public function test_zonder_pandoc_komt_er_een_nette_melding_en_geen_stille_mislukking(): void
    {
        $this->app->instance(Pandoc::class, new Pandoc('pandoc-bestaat-niet'));

        $this->expectException(SchermkopieNietBeschikbaar::class);
        $this->expectExceptionMessage('pandoc ontbreekt');

        $this->kopie([['A.5.1', 'Beleidsregels', 'Ja']], 1)->docx();
    }

    // --- De knop op een scherm ---------------------------------------------

    public function test_zonder_leesrecht_op_het_blok_geen_kopie(): void
    {
        // Medewerker heeft geen enkel niveau op risico-soa (rechtenmatrix).
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($medewerker)
            ->test(KopieerbaarTestscherm::class)
            ->call('kopieerVoorAuditor')
            ->assertForbidden();

        $this->assertSame(0, SchermkopieRegistratie::count());
    }

    public function test_een_mislukte_conversie_legt_niets_vast(): void
    {
        $this->app->instance(Pandoc::class, new Pandoc('pandoc-bestaat-niet'));

        Livewire::actingAs($this->ciso)
            ->test(KopieerbaarTestscherm::class)
            ->call('kopieerVoorAuditor')
            ->assertOk()
            ->assertSet('kopiefout', 'Het Word-document kan niet worden gemaakt: pandoc ontbreekt op deze server.');

        $this->assertSame(0, SchermkopieRegistratie::count(),
            'Een kopie die niet is gemaakt, is ook niet meegegeven.');
    }

    // --- Het overdrachtsregister (§9) --------------------------------------

    public function test_het_overzicht_toont_wat_er_is_meegegeven(): void
    {
        $this->actingAs($this->ciso);
        $this->kopie(array_fill(0, 36, ['A.5.1', 'Beleidsregels', 'Ja']), 93, ['Thema' => 'organisatorisch'])
            ->legVast();

        $this->get('/schermkopieen')
            ->assertOk()
            ->assertSee('Verklaring van Toepasselijkheid')
            ->assertSee('36 van 93 regels')
            ->assertSee('Dana Wolters');
    }

    public function test_wie_niet_alles_mag_zien_komt_niet_op_het_overzicht(): void
    {
        // Medewerker heeft `uitvoeren` op dit blok (eigen bewijs uploaden) en
        // zou anders kunnen doorlezen wat een ander heeft meegegeven.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)->get('/schermkopieen')->assertForbidden();
    }

    public function test_een_geslaagde_kopie_levert_een_document_en_een_vastlegging(): void
    {
        if (! (new Pandoc)->beschikbaar()) {
            $this->markTestSkipped('pandoc staat niet op deze machine.');
        }

        Livewire::actingAs($this->ciso)
            ->test(KopieerbaarTestscherm::class)
            ->call('kopieerVoorAuditor')
            ->assertFileDownloaded();

        $this->assertSame(1, SchermkopieRegistratie::count());
    }
}

/**
 * Een scherm bestaat er nog niet: de uitrol gaat er één voor één op aanwijzing
 * (12h §3). Dit stukje scherm bestaat alleen om het mechanisme te toetsen.
 */
class KopieerbaarTestscherm extends Component
{
    use LevertSchermkopie;

    public function render(): string
    {
        return '<div>@include(\'partials.kopieknop\')</div>';
    }

    protected function kopieBlok(): string
    {
        return 'risico-soa';
    }

    protected function schermkopie(): Schermkopie
    {
        return new Schermkopie(
            scherm: 'Testscherm',
            kolommen: ['Referentie'],
            rijen: [['A.5.1']],
            totaalRijen: 1,
        );
    }
}
