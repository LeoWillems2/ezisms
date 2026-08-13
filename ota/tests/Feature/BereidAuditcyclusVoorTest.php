<?php

namespace Tests\Feature;

use App\Models\Auditobject;
use App\Models\Auditplan;
use App\Models\Auditprogramma;
use App\Models\Auditronde;
use App\Models\Maatregel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BereidAuditcyclusVoorTest extends TestCase
{
    use RefreshDatabase;

    public function test_bereidt_de_volledige_cyclus_met_volledige_dekking_voor(): void
    {
        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2026, '--jaren' => 3])
            ->assertSuccessful();

        $programma = Auditprogramma::firstOrFail();
        $this->assertSame('concept', $programma->status);
        $this->assertSame('2026-01-01', $programma->start_datum->toDateString());
        $this->assertSame('2028-12-31', $programma->eindDatum()->toDateString());

        // Jaarplan per jaar, gekoppeld; ronde per jaar, allemaal gepland.
        $this->assertSame(3, $programma->auditplannen()->count());
        $this->assertSame(3, Auditronde::where('status', 'gepland')->count());

        // Volledige dekking: elk actief object precies één keer over de rondes.
        $actief = Auditobject::actief()->pluck('id')->sort()->values();
        $gedekt = Auditronde::with('auditobjecten:id')->get()
            ->flatMap(fn (Auditronde $r) => $r->auditobjecten->pluck('id'));

        $this->assertSame($actief->all(), $gedekt->sort()->values()->all());
        // Geen dubbeling: som van de scope-groottes == aantal objecten.
        $this->assertSame($actief->count(), $gedekt->count());

        // Dekkingsplanning voor elk object.
        $this->assertSame($actief->count(), $programma->dekkingen()->count());
    }

    public function test_laat_de_interne_auditor_open(): void
    {
        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2026])->assertSuccessful();

        $this->assertSame(0, Auditronde::whereNotNull('auditor_gebruiker_id')->count());
    }

    public function test_activeer_optie_zet_het_programma_op_actief(): void
    {
        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2026, '--activeer' => true])
            ->assertSuccessful();

        $this->assertSame('actief', Auditprogramma::firstOrFail()->status);
    }

    public function test_weigert_een_tweede_cyclus_met_dezelfde_naam(): void
    {
        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2026])->assertSuccessful();
        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2026])->assertFailed();

        $this->assertSame(1, Auditprogramma::count());
    }

    public function test_vervang_vervangt_een_gelijknamige_cyclus(): void
    {
        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2026])->assertSuccessful();
        $oudeId = Auditprogramma::firstOrFail()->id;

        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2026, '--vervang' => true])
            ->assertSuccessful();

        // Precies één programma; het oude is opgeruimd, de rondes vers (niet 6).
        $this->assertSame(1, Auditprogramma::count());
        $this->assertFalse(Auditprogramma::whereKey($oudeId)->exists());
        $this->assertSame(3, Auditronde::count());
    }

    public function test_vervang_ruimt_een_conflicterend_jaarplan_op(): void
    {
        $ander = Auditprogramma::factory()->create(['start_datum' => '2026-01-01', 'aantal_jaren' => 3]);
        Auditplan::factory()->voorProgramma($ander)->create();

        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2026, '--naam' => 'Nieuw', '--vervang' => true])
            ->assertSuccessful();

        $this->assertSame(1, Auditprogramma::count());
        $this->assertFalse(Auditprogramma::whereKey($ander->id)->exists());
        $this->assertSame('Nieuw', Auditprogramma::firstOrFail()->naam);
    }

    public function test_vervang_waarschuwt_voor_jaren_buiten_het_venster_en_stopt_bij_nee(): void
    {
        // Lopende cyclus 2026–2028; nieuwe start in 2027 → 2026 valt buiten het venster.
        $bestaand = Auditprogramma::factory()->create(['naam' => 'Bestaand', 'start_datum' => '2026-01-01', 'aantal_jaren' => 3]);
        foreach ([1, 2, 3] as $nummer) {
            Auditplan::factory()->voorProgramma($bestaand, $nummer)->create();
        }

        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2027, '--vervang' => true])
            ->expectsOutputToContain('2026')
            ->expectsConfirmation('Doorgaan en die jaren ook verwijderen?', 'no')
            ->assertFailed();

        // Niets gewijzigd: de oude cyclus én jaarplan 2026 staan er nog.
        $this->assertTrue(Auditprogramma::whereKey($bestaand->id)->exists());
        $this->assertTrue(Auditplan::where('jaar', 2026)->exists());
        $this->assertSame(1, Auditprogramma::count());
    }

    public function test_vervang_verwijdert_de_collateral_jaren_na_bevestiging(): void
    {
        $bestaand = Auditprogramma::factory()->create(['naam' => 'Bestaand', 'start_datum' => '2026-01-01', 'aantal_jaren' => 3]);
        foreach ([1, 2, 3] as $nummer) {
            Auditplan::factory()->voorProgramma($bestaand, $nummer)->create();
        }

        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2027, '--vervang' => true])
            ->expectsConfirmation('Doorgaan en die jaren ook verwijderen?', 'yes')
            ->assertSuccessful();

        // De oude cyclus is weg, inclusief het collateral-jaar 2026.
        $this->assertFalse(Auditprogramma::whereKey($bestaand->id)->exists());
        $this->assertFalse(Auditplan::where('jaar', 2026)->exists());
        $this->assertSame('Interne auditcyclus 2027–2029', Auditprogramma::firstOrFail()->naam);
    }

    public function test_weigert_als_de_soa_nog_onbeslist_is(): void
    {
        // 5 controls, geen enkele van toepassing verklaard → allemaal onbeslist.
        Maatregel::factory()->count(5)->create();

        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2026])
            ->expectsOutputToContain("staan op 'onbeslist'")
            ->assertFailed();

        $this->assertSame(0, Auditprogramma::count());
    }

    public function test_forceer_gaat_door_ondanks_onbesliste_soa(): void
    {
        Maatregel::factory()->count(5)->create();

        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2026, '--forceer' => true])
            ->assertSuccessful();

        $this->assertSame(1, Auditprogramma::count());
    }

    // --- Plan 11c fase 3: --voorbereiding ---------------------------------

    /**
     * De opstartfase: één plan, één nulmeting over álles, geen dekkingsverdeling
     * over jaren. Precies wat een gap assessment is.
     */
    public function test_voorbereiding_zet_een_nulmeting_over_alle_objecten(): void
    {
        $this->artisan('isms:bereid-auditcyclus-voor', ['--voorbereiding' => true, '--start' => '2027-05-14'])
            ->assertSuccessful();

        $programma = Auditprogramma::firstOrFail();
        $this->assertSame('voorbereiding', $programma->aard);
        $this->assertSame(1, $programma->aantal_jaren);
        $this->assertSame('2027-05-14', $programma->start_datum->toDateString());

        // Geen dekkingsverdeling: een opstartfase kent geen meerjarige
        // dekkingsverplichting.
        $this->assertSame(0, $programma->dekkingen()->count());
        $this->assertSame(1, $programma->auditplannen()->count());

        $ronde = Auditronde::firstOrFail();
        $this->assertSame('intern_nulmeting', $ronde->type);
        $this->assertFalse($ronde->telt_mee_voor_dekking);
        $this->assertSame(Auditobject::actief()->count(), $ronde->auditobjecten()->count());
    }

    /**
     * Zonder deze uitzondering is het command onbruikbaar op het moment waarop je
     * hem het hardst nodig hebt: bij een nulmeting ís de SoA nog niet af — dat is
     * juist wat je gaat meten.
     */
    public function test_voorbereiding_stopt_niet_op_een_onvolledige_soa(): void
    {
        Maatregel::factory()->count(3)->create();
        Maatregel::first()->soaRegel()->create(['van_toepassing' => true]);

        $this->artisan('isms:bereid-auditcyclus-voor', ['--voorbereiding' => true])
            ->expectsOutputToContain('geen blokkade')
            ->assertSuccessful();

        $this->assertSame(1, Auditprogramma::count());
    }

    /** Een voorbereiding mag naast een cyclus liggen; hij loopt er juist voor. */
    public function test_voorbereiding_botst_niet_met_een_lopende_cyclus(): void
    {
        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2027])->assertSuccessful();

        $this->artisan('isms:bereid-auditcyclus-voor', ['--voorbereiding' => true, '--start' => 2027])
            ->assertSuccessful();

        $this->assertSame(2, Auditprogramma::count());
    }

    public function test_start_accepteert_zowel_een_datum_als_een_jaartal(): void
    {
        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => '2027-05-14', '--naam' => 'Datum'])
            ->assertSuccessful();
        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2031, '--naam' => 'Jaartal'])
            ->assertSuccessful();

        $this->assertSame('2027-05-14', Auditprogramma::where('naam', 'Datum')->firstOrFail()->start_datum->toDateString());
        // Een jaartal blijft werken en betekent 1 januari — bestaande scripts
        // en documentatie hoeven niet mee te veranderen.
        $this->assertSame('2031-01-01', Auditprogramma::where('naam', 'Jaartal')->firstOrFail()->start_datum->toDateString());
    }

    public function test_onleesbare_startdatum_wordt_geweigerd(): void
    {
        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 'volgende lente'])
            ->expectsOutputToContain('geen datum')
            ->assertFailed();

        $this->assertSame(0, Auditprogramma::count());
    }

    public function test_weigert_als_een_cyclus_hetzelfde_venster_beslaat(): void
    {
        $ander = Auditprogramma::factory()->create(['start_datum' => '2026-01-01', 'aantal_jaren' => 3]);
        Auditplan::factory()->voorProgramma($ander)->create();

        $this->artisan('isms:bereid-auditcyclus-voor', ['--start' => 2026, '--naam' => 'Nieuw'])
            ->assertFailed();

        // Niets aangemaakt buiten het bestaande programma.
        $this->assertSame(1, Auditprogramma::count());
    }
}
