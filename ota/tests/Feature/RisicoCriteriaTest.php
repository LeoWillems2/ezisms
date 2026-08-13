<?php

namespace Tests\Feature;

use App\Livewire\RisicoCriteria;
use App\Models\Gebruiker;
use App\Models\Risico;
use App\Models\RisicocriteriaVersie;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Het criteriascherm zelf: opstellen, valideren, en wat de vastgestelde grenzen
 * met de semafoor doen. De versiegang en de gevolgen van een activatie staan in
 * RisicocriteriaVersiesTest.
 */
class RisicoCriteriaTest extends TestCase
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

    /** Een bewerkbare conceptversie, zoals de CISO die op het scherm start. */
    private function concept(): Testable
    {
        return Livewire::actingAs($this->ciso)
            ->test(RisicoCriteria::class)
            ->call('nieuweConceptversieStarten');
    }

    // --- Opstellen (§6.1.2) ------------------------------------------------

    public function test_ciso_stelt_appetite_en_grenzen_in_en_het_komt_in_de_audit_trail(): void
    {
        $this->concept()
            ->set('omschrijving', 'Wij accepteren laag en middelmatig risico; hoog risico vereist directiebesluit.')
            ->set('drempelwaardeScore', 12)
            ->set('waarschuwingsdrempelScore', 6)
            ->call('conceptOpslaan')
            ->assertHasNoErrors();

        $concept = RisicocriteriaVersie::where('status', 'concept')->firstOrFail();
        $this->assertSame(2, $concept->versienummer);
        $this->assertSame(12, $concept->drempelwaarde_score);
        $this->assertSame(6, $concept->waarschuwingsdrempel_score);

        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'risicocriteria_versie',
            'entiteit_id' => $concept->id,
            'actie' => 'gewijzigd',
        ]);
    }

    /**
     * Een concept verandert nog niets aan de semafoor: de actieve versie blijft
     * gelden tot de directie de nieuwe vaststelt. Dat is de kern van 04g — de
     * drempel is geen dagwaarde meer.
     */
    public function test_een_concept_verzet_de_drempel_nog_niet(): void
    {
        $this->concept()
            ->set('drempelwaardeScore', 8)
            ->set('waarschuwingsdrempelScore', 4)
            ->call('conceptOpslaan')
            ->assertHasNoErrors();

        $this->assertSame(15, Risico::drempelwaarde());
        $this->assertSame('amber', Risico::scoreKleur(12));
    }

    // --- De grenzen sturen de semafoor (§4) --------------------------------

    public function test_scorekleur_volgt_de_vastgestelde_amber_grens(): void
    {
        // Standaard amber = 10: score 7 is groen.
        $this->assertSame('green', Risico::scoreKleur(7));

        RisicocriteriaVersie::actief()->update(['waarschuwingsdrempel_score' => 6]);
        RisicocriteriaVersie::vergeet();

        // Amber verlaagd naar 6: score 7 wordt nu amber.
        $this->assertSame('amber', Risico::scoreKleur(7));
    }

    // --- Validatie ---------------------------------------------------------

    public function test_amber_grens_mag_niet_boven_de_acceptatiedrempel(): void
    {
        $this->concept()
            ->set('drempelwaardeScore', 10)
            ->set('waarschuwingsdrempelScore', 15)
            ->call('conceptOpslaan')
            ->assertHasErrors(['waarschuwingsdrempelScore']);
    }

    public function test_grenzen_blijven_binnen_de_schaal(): void
    {
        $this->concept()
            ->set('drempelwaardeScore', 30)
            ->call('conceptOpslaan')
            ->assertHasErrors(['drempelwaardeScore']);
    }

    public function test_een_niveau_zonder_naam_wordt_geweigerd(): void
    {
        $this->concept()
            ->set('niveaus.impact.4.naam', '')
            ->call('conceptOpslaan')
            ->assertHasErrors(['niveaus.impact.4.naam']);
    }

    /**
     * De kwantitatieve band is optioneel en blijft dat: het ISMS levert geen
     * omzetpercentage mee en gaat er ook niet om vragen (04g §2.3).
     */
    public function test_de_kwantitatieve_band_is_optioneel_en_wordt_bewaard(): void
    {
        $this->concept()
            ->set('niveaus.impact.4.kwantitatieve_band', '1 tot 5% van de jaaromzet')
            ->call('conceptOpslaan')
            ->assertHasNoErrors();

        $concept = RisicocriteriaVersie::where('status', 'concept')->firstOrFail();

        $this->assertSame(
            '1 tot 5% van de jaaromzet',
            $concept->niveausVan('impact')[4]->kwantitatieve_band,
        );
        // De rest blijft leeg; niets wordt stilzwijgend ingevuld.
        $this->assertNull($concept->niveausVan('impact')[5]->kwantitatieve_band);
    }

    // --- Autorisatie -------------------------------------------------------

    public function test_auditor_leest_maar_muteert_niet(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/risicos/criteria')->assertOk();

        Livewire::actingAs($auditor)
            ->test(RisicoCriteria::class)
            ->call('nieuweConceptversieStarten')
            ->assertForbidden();
    }
}
