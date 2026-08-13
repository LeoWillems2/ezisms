<?php

namespace Tests\Feature;

use App\Livewire\RisicoMatrix;
use App\Models\Gebruiker;
use App\Models\Risico;
use App\Models\RisicocriteriaVersie;
use App\Support\Risicoverdeling;
use App\Support\Schermkopie;
use App\Support\Tolerantiematrixplaat;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RisicomatrixTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class,
            RisicocriteriaSeeder::class,
        ]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    // --- Celtelling (§3/§8) ------------------------------------------------

    public function test_beoordeelde_risicos_landen_in_de_juiste_cel(): void
    {
        Risico::factory()->beoordeeld(4, 4)->create();

        Livewire::actingAs($this->ciso)
            ->test(RisicoMatrix::class)
            ->assertViewHas('tellers', fn ($t) => ($t[4][4] ?? 0) === 1);

        Risico::factory()->beoordeeld(4, 4)->create();

        Livewire::actingAs($this->ciso)
            ->test(RisicoMatrix::class)
            ->assertViewHas('tellers', fn ($t) => ($t[4][4] ?? 0) === 2);
    }

    // --- Niet beoordeeld (§3) ----------------------------------------------

    public function test_onbeoordeeld_risico_valt_buiten_de_matrix_maar_wordt_geteld(): void
    {
        // Kans wel, impact niet: geen volledige beoordeling.
        Risico::factory()->create(['kans_niveau' => 3, 'impact_niveau' => null]);

        Livewire::actingAs($this->ciso)
            ->test(RisicoMatrix::class)
            ->assertViewHas('nietBeoordeeldAantal', 1)
            ->assertViewHas('tellers', fn ($t) => $t === []);
    }

    // --- Kleur: één bron van waarheid (§4) ---------------------------------

    public function test_scorekleur_volgt_de_banden_en_de_drempel(): void
    {
        // Drempel 15 (geseed), waarschuwing 10.
        $this->assertSame('zinc', Risico::scoreKleur(null));
        $this->assertSame('green', Risico::scoreKleur(9));
        $this->assertSame('amber', Risico::scoreKleur(10));
        $this->assertSame('amber', Risico::scoreKleur(15));
        $this->assertSame('red', Risico::scoreKleur(16));

        // Regressie op de oude hardcoded 15: verlaag de drempel en de rode band
        // schuift mee. Score 12 was amber, wordt nu rood.
        RisicocriteriaVersie::query()->update(['drempelwaarde_score' => 8]);
        RisicocriteriaVersie::vergeet();

        $this->assertSame('red', Risico::scoreKleur(12));
        $this->assertSame('green', Risico::scoreKleur(7));
    }

    // --- Klik-filter (§5) --------------------------------------------------

    public function test_cel_selecteren_toont_alleen_die_risicos_en_deselecteren_verbergt_de_lijst(): void
    {
        $inCel = Risico::factory()->beoordeeld(4, 4)->create(['titel' => 'Risico in de cel']);
        Risico::factory()->beoordeeld(2, 2)->create(['titel' => 'Risico elders']);

        Livewire::actingAs($this->ciso)
            ->test(RisicoMatrix::class)
            ->assertViewHas('geselecteerdeRisicos', null)
            ->call('selecteerCel', 4, 4)
            ->assertViewHas('geselecteerdeRisicos', fn ($r) => $r->pluck('id')->all() === [$inCel->id])
            ->assertSee('Risico in de cel')
            ->assertDontSee('Risico elders')
            // Nogmaals dezelfde cel: deselecteren.
            ->call('selecteerCel', 4, 4)
            ->assertViewHas('geselecteerdeRisicos', null);
    }

    public function test_niet_beoordeeld_selectie_toont_de_onbeoordeelde_risicos(): void
    {
        Risico::factory()->create(['titel' => 'Onbeoordeeld', 'kans_niveau' => null, 'impact_niveau' => null]);
        Risico::factory()->beoordeeld(3, 3)->create(['titel' => 'Wel beoordeeld']);

        Livewire::actingAs($this->ciso)
            ->test(RisicoMatrix::class)
            ->call('selecteerNietBeoordeeld')
            ->assertViewHas('geselecteerdeRisicos', fn ($r) => $r->count() === 1)
            ->assertSee('Onbeoordeeld')
            ->assertDontSee('Wel beoordeeld');
    }

    // --- Autorisatie (§8) --------------------------------------------------

    public function test_auditor_mag_de_matrix_lezen(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/risicos/matrix')->assertOk();
    }

    public function test_matrix_route_wint_van_de_model_binding(): void
    {
        // /risicos/matrix mag niet als risicos/{risico} worden opgevat.
        $this->actingAs($this->ciso)->get('/risicos/matrix')->assertOk();
    }

    // --- Kopie voor de auditor (12h §11 test 7) ----------------------------

    /** De kopie is `protected`; het scherm bouwt hem, de trait roept hem aan. */
    private function matrixKopie(RisicoMatrix $component): Schermkopie
    {
        return (fn (): Schermkopie => $this->schermkopie())->call($component);
    }

    public function test_de_kopie_bevat_de_matrix_zoals_het_scherm_hem_toont(): void
    {
        Risico::factory()->count(2)->beoordeeld(4, 5)->create();
        Risico::factory()->beoordeeld(1, 1)->create();

        $component = Livewire::actingAs($this->ciso)->test(RisicoMatrix::class);
        $markdown = $this->matrixKopie($component->instance())->markdown();

        $this->assertStringContainsString('# Tolerantiematrix', $markdown);
        $this->assertStringContainsString('| Impact | Kans 1 | Kans 2 | Kans 3 | Kans 4 | Kans 5 |', $markdown);
        // Hoogste impact bovenaan, net als op het scherm (04b §3).
        $this->assertStringContainsString('| 5 | 0 | 0 | 0 | 2 | 0 |', $markdown);
        $this->assertStringContainsString('| 1 | 1 | 0 | 0 | 0 | 0 |', $markdown);
        $this->assertStringContainsString('| Omvang | Alle 5 impactniveaus. |', $markdown);
    }

    /**
     * De toelichting draagt wat er niet in de matrix past: de drempels, en de
     * risico's die er buiten vallen. Zwijgen over die laatste zou het document
     * completer laten lijken dan het register is.
     */
    public function test_de_kopie_noemt_de_drempels_en_de_niet_beoordeelde_risicos(): void
    {
        Risico::factory()->beoordeeld(5, 5)->create();
        Risico::factory()->create(['kans_niveau' => null, 'impact_niveau' => 3]);

        $component = Livewire::actingAs($this->ciso)->test(RisicoMatrix::class);
        $markdown = $this->matrixKopie($component->instance())->markdown();

        $this->assertStringContainsString('boven '.Risico::drempelwaarde(), $markdown);
        $this->assertStringContainsString('vanaf '.Risico::waarschuwingsdrempel(), $markdown);
        $this->assertStringContainsString('1 risico(\'s) zijn nog niet beoordeeld', $markdown);
    }

    /**
     * De celselectie filtert op het scherm alleen de risicolijst *onder* de
     * matrix. De matrix zelf blijft volledig, dus het document mag geen filter
     * in de kop zetten — dat zou beweren dat er iets weggelaten is (12h §4).
     */
    public function test_de_celselectie_filtert_de_kopie_niet(): void
    {
        Risico::factory()->beoordeeld(4, 5)->create();
        Risico::factory()->beoordeeld(1, 1)->create();

        $component = Livewire::actingAs($this->ciso)
            ->test(RisicoMatrix::class)
            ->call('selecteerCel', 4, 5);

        $kopie = $this->matrixKopie($component->instance());

        $this->assertSame([], $kopie->filters);
        $this->assertStringContainsString('| 1 | 1 | 0 | 0 | 0 | 0 |', $kopie->markdown());
    }

    /**
     * Het plaatje is de reden dat dit scherm een kopie waard is: waar de massa
     * ligt ten opzichte van de drempel zie je aan de kleur, en dat overleeft
     * geen omzetting naar rijen.
     */
    public function test_de_matrix_gaat_als_plaatje_mee(): void
    {
        Risico::factory()->beoordeeld(5, 5)->create();

        $plaat = Tolerantiematrixplaat::teken(Risicoverdeling::huidige());

        if ($plaat === null) {
            $this->markTestSkipped('Geen GD of geen TTF-lettertype op deze machine.');
        }

        // Werkelijk een PNG, en groot genoeg om een 5×5-raster te zijn.
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($plaat->png, 0, 8));
        [$breedte, $hoogte] = getimagesizefromstring($plaat->png);
        $this->assertGreaterThan(600, $breedte);
        $this->assertGreaterThan(600, $hoogte);

        $component = Livewire::actingAs($this->ciso)->test(RisicoMatrix::class);
        $this->assertStringContainsString(
            'data:image/png;base64,',
            $this->matrixKopie($component->instance())->markdown(),
        );
    }

    public function test_zonder_leesrecht_op_de_risicos_geen_kopie(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $component = Livewire::actingAs($this->ciso)->test(RisicoMatrix::class);

        $this->actingAs($medewerker);
        $this->assertFalse($component->instance()->magKopieren());
    }
}
