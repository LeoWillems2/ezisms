<?php

namespace Tests\Feature;

use App\Models\Afwijking;
use App\Models\AuditLogregel;
use App\Models\Auditronde;
use App\Models\Bevinding;
use App\Models\BewijsKoppeling;
use App\Models\Bewijsstuk;
use App\Models\CorrigerendeMaatregel;
use App\Models\Doelgroep;
use App\Models\Gebruiker;
use App\Models\Issue;
use App\Models\KpiDefinitie;
use App\Models\Maatregel;
use App\Models\Meting;
use App\Models\Risico;
use App\Models\ScopeVerklaring;
use App\Models\SoaRegel;
use App\Models\Taak;
use App\Models\Trainingsmodule;
use App\Models\Trainingsvoltooiing;
use App\Support\Meetbronnen;
use Database\Seeders\KpiDefinitieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KpiMetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(KpiDefinitieSeeder::class);
    }

    private function soaRegel(?bool $vanToepassing, array $extra = []): SoaRegel
    {
        return SoaRegel::create([
            'maatregel_id' => Maatregel::factory()->create()->id,
            'van_toepassing' => $vanToepassing,
        ] + $extra);
    }

    private function meting(string $sleutel): ?Meting
    {
        return Meting::whereHas('kpiDefinitie', fn ($q) => $q->where('sleutel', $sleutel))->first();
    }

    public function test_command_legt_teller_en_noemer_vast(): void
    {
        $this->soaRegel(true);
        $this->soaRegel(false);
        $this->soaRegel(null); // onbeslist telt niet als beoordeeld

        $this->artisan('isms:meet-kpis')->assertSuccessful();

        $meting = $this->meting('soa_beoordeeld');
        $this->assertNotNull($meting);
        $this->assertSame(2, $meting->teller);
        $this->assertSame(3, $meting->noemer);
        $this->assertSame(1, $meting->definitie_versie);
    }

    public function test_metingen_zijn_idempotent_binnen_een_maand(): void
    {
        $this->soaRegel(true);

        $this->artisan('isms:meet-kpis');
        $this->artisan('isms:meet-kpis');

        $this->assertSame(1, Meting::whereHas(
            'kpiDefinitie', fn ($q) => $q->where('sleutel', 'soa_beoordeeld')
        )->count());
    }

    public function test_kpi_zonder_populatie_wordt_overgeslagen(): void
    {
        // Wel SoA-regels, geen risico's en geen taken.
        $this->soaRegel(true);

        $this->artisan('isms:meet-kpis');

        $this->assertNotNull($this->meting('soa_beoordeeld'));
        $this->assertNull($this->meting('risico_met_eigenaar_en_plan'));
        $this->assertNull($this->meting('reviewtaken_op_tijd'));
    }

    public function test_risico_kpi_telt_eigenaar_en_behandeling(): void
    {
        $eigenaar = Gebruiker::factory()->create();

        $metPlan = Risico::factory()->create(['risico_eigenaar_id' => $eigenaar->id]);
        $metPlan->behandelingen()->create(['behandeloptie' => 'mitigeren']);

        Risico::factory()->create(['risico_eigenaar_id' => $eigenaar->id]); // eigenaar, geen plan
        Risico::factory()->create(); // niets

        $this->artisan('isms:meet-kpis');

        $meting = $this->meting('risico_met_eigenaar_en_plan');
        $this->assertSame(1, $meting->teller);
        $this->assertSame(3, $meting->noemer);
    }

    public function test_reviewtaken_op_tijd_en_gemiddelde_overschrijding(): void
    {
        // Beheerd (soort ≠ null), voltooid: op tijd.
        Taak::factory()->create([
            'soort' => 'soa-herbeoordeling', 'status' => 'voltooid',
            'deadline' => Carbon::today(), 'voltooid_op' => Carbon::today(),
        ]);
        // Beheerd, voltooid: 5 dagen te laat.
        Taak::factory()->create([
            'soort' => 'soa-herbeoordeling', 'status' => 'voltooid',
            'deadline' => Carbon::today()->subDays(5), 'voltooid_op' => Carbon::today(),
        ]);
        // Onbeheerde taak (soort null): telt niet mee.
        Taak::factory()->create([
            'soort' => null, 'status' => 'voltooid',
            'deadline' => Carbon::today()->subDays(9), 'voltooid_op' => Carbon::today(),
        ]);

        $this->artisan('isms:meet-kpis');

        $opTijd = $this->meting('reviewtaken_op_tijd');
        $this->assertSame(1, $opTijd->teller);
        $this->assertSame(2, $opTijd->noemer);

        $overschrijding = $this->meting('reviewtaken_gem_overschrijding');
        $this->assertSame(5, $overschrijding->teller); // 0 + 5
        $this->assertSame(2, $overschrijding->noemer);
        $this->assertSame(2.5, $overschrijding->gemiddelde());
    }

    public function test_metingen_vormen_een_onveranderlijke_reeks(): void
    {
        Carbon::setTestNow('2026-07-15');
        $this->soaRegel(true); // 1 beoordeeld / 1 totaal
        $this->artisan('isms:meet-kpis');

        $eerste = $this->meting('soa_beoordeeld');
        $this->assertSame(1, $eerste->noemer);

        // Volgende maand, met gewijzigde data: een nieuw meetpunt, niet een
        // herberekening van het oude.
        Carbon::setTestNow('2026-08-15');
        $this->soaRegel(false); // nu 2 totaal
        $this->artisan('isms:meet-kpis');

        $this->assertSame(2, Meting::whereHas(
            'kpiDefinitie', fn ($q) => $q->where('sleutel', 'soa_beoordeeld')
        )->count());

        $this->assertSame(1, $eerste->fresh()->noemer); // ongewijzigd
        $laatste = Meting::whereHas('kpiDefinitie', fn ($q) => $q->where('sleutel', 'soa_beoordeeld'))
            ->latest('gemeten_op')->first();
        $this->assertSame(2, $laatste->noemer);

        Carbon::setTestNow();
    }

    public function test_percentage_wordt_afgeleid_niet_opgeslagen(): void
    {
        $this->soaRegel(true);
        $this->soaRegel(true);
        $this->soaRegel(null);

        $this->artisan('isms:meet-kpis');

        // 2 van 3 beoordeeld.
        $this->assertSame(66.7, $this->meting('soa_beoordeeld')->percentage());
    }

    /**
     * De norm gaat mee de meetrij in (12d §2b), net als de definitieversie —
     * anders wordt de status van historische punten tegen de huidige norm
     * berekend. Maar alleen een **vastgestelde** norm (12e §9): de streefwaarde
     * uit de seeder is een voorstel en kleurt niets.
     */
    public function test_alleen_een_vastgestelde_norm_komt_op_de_meetrij(): void
    {
        Carbon::setTestNow('2026-07-15');
        $this->soaRegel(true);

        $this->artisan('isms:meet-kpis');

        // De seeder levert streef 100 / signaal 95, maar niemand stelde ze vast.
        $definitie = KpiDefinitie::where('sleutel', 'soa_beoordeeld')->firstOrFail();
        $this->assertSame(100.0, $definitie->streefwaarde);
        $this->assertFalse($definitie->streefwaardeIsVastgesteld());
        $this->assertNull($this->meting('soa_beoordeeld')->streefwaarde);

        // Na vaststelling telt hij vanaf het eerstvolgende meetpunt mee.
        $definitie->update(['streefwaarde_vastgesteld_op' => Carbon::today()]);

        Carbon::setTestNow('2026-08-15');
        $this->artisan('isms:meet-kpis');

        $laatste = $definitie->metingen()->latest('gemeten_op')->firstOrFail();
        $this->assertSame(100.0, $laatste->streefwaarde);
        $this->assertSame(95.0, $laatste->signaalwaarde);

        Carbon::setTestNow();
    }

    /** Een KPI zonder streefwaarde laat de kolom leeg in plaats van er een 0 in te schrijven. */
    public function test_een_kpi_zonder_norm_laat_de_kolom_leeg(): void
    {
        $this->soaRegel(true, ['van_toepassing' => true, 'implementatiestatus' => 'geimplementeerd']);

        $this->artisan('isms:meet-kpis');

        $this->assertNull($this->meting('soa_geimplementeerd')?->streefwaarde);
    }

    // --- Bronbreedte (implementatie/12d §4) ---------------------------------

    public function test_risico_boven_de_drempel_telt_alleen_risicos_boven_de_drempel(): void
    {
        // De observer leidt de score af uit kans × impact; de standaarddrempel
        // is 15, dus 4 × 5 = 20 staat erboven en 3 × 5 = 15 er precies op.
        $metPlan = Risico::factory()->beoordeeld(4, 5)->create();
        $metPlan->behandelingen()->create(['behandeloptie' => 'mitigeren']);

        Risico::factory()->beoordeeld(4, 4)->create();   // 16: boven, geen plan
        Risico::factory()->beoordeeld(3, 5)->create();   // 15: op de drempel telt niet mee
        Risico::factory()->beoordeeld(1, 1)->create();

        $this->artisan('isms:meet-kpis');

        $meting = $this->meting('risico_boven_drempel_met_plan');
        $this->assertSame(1, $meting->teller);
        $this->assertSame(2, $meting->noemer);
    }

    /**
     * Geen risico's boven de acceptatiedrempel is een normale toestand en geen
     * randgeval. Geen meetpunt is dan het juiste antwoord — 100% zou suggereren
     * dat er iets goed ging (12d §4).
     */
    public function test_risico_boven_de_drempel_schrijft_niets_zonder_populatie(): void
    {
        Risico::factory()->beoordeeld(1, 1)->create();

        $this->artisan('isms:meet-kpis')->assertSuccessful();

        $this->assertNull($this->meting('risico_boven_drempel_met_plan'));
    }

    public function test_context_telt_issues_en_actieve_scopeverklaringen_samen(): void
    {
        Issue::factory()->create(['laatst_beoordeeld_op' => Carbon::today()->subMonths(3)]);
        Issue::factory()->create(['laatst_beoordeeld_op' => Carbon::today()->subMonths(18)]);
        Issue::factory()->create(['laatst_beoordeeld_op' => null]);

        ScopeVerklaring::factory()->create([
            'status' => 'actief',
            'volgende_herziening_gepland' => Carbon::today()->addMonth(),
        ]);
        ScopeVerklaring::factory()->create([
            'status' => 'actief',
            'volgende_herziening_gepland' => Carbon::today()->subMonth(),
        ]);
        // Een vervangen verklaring telt in geen van beide mee.
        ScopeVerklaring::factory()->create([
            'status' => 'vervangen',
            'volgende_herziening_gepland' => Carbon::today()->addMonth(),
        ]);

        $this->artisan('isms:meet-kpis');

        $meting = $this->meting('context_binnen_herzieningstermijn');
        $this->assertSame(2, $meting->teller);   // 1 issue + 1 scopeverklaring
        $this->assertSame(5, $meting->noemer);   // 3 issues + 2 actieve verklaringen
    }

    public function test_trainingsgraad_negeert_verlopen_voltooiingen_en_inactieven(): void
    {
        $module = Trainingsmodule::factory()->create(['actief' => true]);
        $inactieveModule = Trainingsmodule::factory()->create(['actief' => false]);
        $doelgroep = Doelgroep::factory()->create();
        $doelgroep->modules()->attach([$module->id, $inactieveModule->id]);

        $afgerond = Gebruiker::factory()->create(['status' => 'actief']);
        $verlopen = Gebruiker::factory()->create(['status' => 'actief']);
        $niets = Gebruiker::factory()->create(['status' => 'actief']);
        $uitDienst = Gebruiker::factory()->create(['status' => 'gedeactiveerd']);

        $doelgroep->gebruikers()->attach([$afgerond->id, $verlopen->id, $niets->id, $uitDienst->id]);

        Trainingsvoltooiing::factory()->create([
            'trainingsmodule_id' => $module->id, 'gebruiker_id' => $afgerond->id,
            'verloopt_op' => Carbon::today()->addMonths(6),
        ]);
        Trainingsvoltooiing::factory()->create([
            'trainingsmodule_id' => $module->id, 'gebruiker_id' => $verlopen->id,
            'verloopt_op' => Carbon::today()->subDay(),
        ]);
        // Een voltooiing op de inactieve module telt nergens mee.
        Trainingsvoltooiing::factory()->create([
            'trainingsmodule_id' => $inactieveModule->id, 'gebruiker_id' => $niets->id,
            'verloopt_op' => null,
        ]);

        $this->artisan('isms:meet-kpis');

        $meting = $this->meting('trainingsgraad');
        $this->assertSame(1, $meting->teller);   // alleen de geldige voltooiing
        $this->assertSame(3, $meting->noemer);   // drie actieve medewerkers × één actieve module
    }

    public function test_soa_met_bewijs_telt_alleen_geimplementeerde_regels_met_koppeling(): void
    {
        $metBewijs = $this->soaRegel(true, ['implementatiestatus' => 'geimplementeerd']);
        $this->soaRegel(true, ['implementatiestatus' => 'geimplementeerd']);
        // Niet geïmplementeerd: valt buiten teller én noemer.
        $buitenBeeld = $this->soaRegel(true, ['implementatiestatus' => 'niet_gestart']);

        foreach ([$metBewijs, $buitenBeeld] as $regel) {
            BewijsKoppeling::create([
                'bewijsstuk_id' => Bewijsstuk::factory()->create()->id,
                'blok_naam' => 'soa-risicobehandeling',
                'entiteit_type' => 'soa_regel',
                'entiteit_id' => $regel->id,
            ]);
        }

        $this->artisan('isms:meet-kpis');

        $meting = $this->meting('soa_geimplementeerd_met_bewijs');
        $this->assertSame(1, $meting->teller);
        $this->assertSame(2, $meting->noemer);
    }

    public function test_openstaande_bevindingen_tellen_alles_wat_niet_gesloten_is(): void
    {
        $ronde = Auditronde::factory()->create();

        foreach (['open', 'non_conformiteit_gestart', 'gesloten'] as $status) {
            Bevinding::factory()->create(['auditronde_id' => $ronde->id, 'status' => $status]);
        }

        $this->artisan('isms:meet-kpis');

        $meting = $this->meting('bevindingen_open');
        $this->assertSame(2, $meting->teller);
        $this->assertSame(3, $meting->noemer);

        // Richting omlaag: dit is de ratio-KPI die 12d fase 1 rechtvaardigt.
        $this->assertSame('omlaag', $meting->kpiDefinitie->richting);
    }

    public function test_dagen_sinds_interne_audit_rekent_vanaf_de_laatste_uitgevoerde_ronde(): void
    {
        Auditronde::factory()->create([
            'type' => 'intern', 'uitgevoerd_op' => Carbon::today()->subDays(90),
        ]);
        Auditronde::factory()->create([
            'type' => 'intern_nulmeting', 'uitgevoerd_op' => Carbon::today()->subDays(40),
        ]);
        // Een externe ronde telt niet mee, ook niet als hij recenter is.
        Auditronde::factory()->create([
            'type' => 'extern_certificering', 'uitgevoerd_op' => Carbon::today()->subDays(5),
        ]);

        $this->artisan('isms:meet-kpis');

        $meting = $this->meting('dagen_sinds_interne_audit');
        $this->assertSame(40, $meting->teller);
        $this->assertSame(1, $meting->noemer);
        $this->assertSame(40.0, $meting->gemiddelde());
    }

    public function test_dagen_sinds_interne_audit_schrijft_niets_zonder_uitgevoerde_ronde(): void
    {
        Auditronde::factory()->create(['type' => 'intern', 'uitgevoerd_op' => null]);

        $this->artisan('isms:meet-kpis')->assertSuccessful();

        $this->assertNull($this->meting('dagen_sinds_interne_audit'));
    }

    public function test_capa_kpis_meten_tijdigheid_en_doorlooptijd(): void
    {
        $afwijking = Afwijking::factory()->create();

        // Op tijd, doorlooptijd 10 dagen.
        CorrigerendeMaatregel::factory()->create([
            'afwijking_id' => $afwijking->id, 'status' => 'voltooid',
            'created_at' => Carbon::today()->subDays(10),
            'deadline' => Carbon::today()->addDay(), 'voltooid_op' => Carbon::today(),
        ]);
        // Te laat, doorlooptijd 20 dagen.
        CorrigerendeMaatregel::factory()->create([
            'afwijking_id' => $afwijking->id, 'status' => 'voltooid',
            'created_at' => Carbon::today()->subDays(20),
            'deadline' => Carbon::today()->subDays(5), 'voltooid_op' => Carbon::today(),
        ]);
        // Nog lopend: telt in geen van beide mee.
        CorrigerendeMaatregel::factory()->create([
            'afwijking_id' => $afwijking->id, 'status' => 'in_uitvoering',
            'deadline' => Carbon::today()->addDays(30), 'voltooid_op' => null,
        ]);

        $this->artisan('isms:meet-kpis');

        $opTijd = $this->meting('capa_op_tijd');
        $this->assertSame(1, $opTijd->teller);
        $this->assertSame(2, $opTijd->noemer);

        $doorlooptijd = $this->meting('capa_doorlooptijd');
        $this->assertSame(30, $doorlooptijd->teller); // 10 + 20
        $this->assertSame(2, $doorlooptijd->noemer);
        $this->assertSame(15.0, $doorlooptijd->gemiddelde());
    }

    /**
     * Zonder deadline is "op tijd" niet te bepalen; die maatregel hoort in geen
     * van beide kanten van de breuk te staan.
     */
    public function test_capa_op_tijd_negeert_een_maatregel_zonder_deadline(): void
    {
        CorrigerendeMaatregel::factory()->create([
            'afwijking_id' => Afwijking::factory()->create()->id, 'status' => 'voltooid',
            'deadline' => null, 'voltooid_op' => Carbon::today(),
        ]);

        $this->artisan('isms:meet-kpis');

        $this->assertNull($this->meting('capa_op_tijd'));
        // De doorlooptijd is wél te bepalen zonder deadline.
        $this->assertNotNull($this->meting('capa_doorlooptijd'));
    }

    /** De Act-fase is niet langer leeg (12d §5). */
    public function test_de_act_fase_heeft_metingen(): void
    {
        $this->assertSame(5, KpiDefinitie::where('fase', 'act')->where('actief', true)->count());
    }

    // --- Act op gebeurtenissen (implementatie/12g) --------------------------

    private function risicoTrail(Risico $risico, array $oud, array $nieuw, string $actie = 'gewijzigd'): void
    {
        AuditLogregel::create([
            'tijdstip' => now(),
            'gebruiker_naam' => 'Testgebruiker',
            'blok_naam' => 'risico-soa',
            'entiteit_type' => 'risico',
            'entiteit_id' => $risico->id,
            'entiteit_omschrijving' => $risico->titel ?? 'risico',
            'actie' => $actie,
            'oude_waarde' => $oud,
            'nieuwe_waarde' => $nieuw,
        ]);
    }

    public function test_scoredaling_telt_alleen_zonder_bewijs_in_dezelfde_periode(): void
    {
        $zonder = Risico::factory()->beoordeeld(3, 4)->create();
        $met = Risico::factory()->beoordeeld(3, 4)->create();

        $this->risicoTrail($zonder, ['risicoscore' => 12], ['risicoscore' => 6]);
        $this->risicoTrail($met, ['risicoscore' => 12], ['risicoscore' => 6]);

        BewijsKoppeling::create([
            'bewijsstuk_id' => Bewijsstuk::factory()->create()->id,
            'blok_naam' => 'risico-soa',
            'entiteit_type' => 'risico',
            'entiteit_id' => $met->id,
        ]);

        $this->artisan('isms:meet-kpis');

        $meting = $this->meting('scoredaling_zonder_bewijs');
        $this->assertSame(1, $meting->teller);
        $this->assertSame(2, $meting->noemer);
    }

    /** Een eerste beoordeling gaat van geen score naar een score; dat is geen daling. */
    public function test_een_eerste_beoordeling_en_een_stijging_tellen_niet_als_daling(): void
    {
        $risico = Risico::factory()->beoordeeld(3, 4)->create();

        $this->risicoTrail($risico, ['risicoscore' => null], ['risicoscore' => 12]);
        $this->risicoTrail($risico, ['risicoscore' => 6], ['risicoscore' => 12]);

        $this->artisan('isms:meet-kpis')->assertSuccessful();

        $this->assertNull($this->meting('scoredaling_zonder_bewijs'));
    }

    /**
     * `Auditeerbaar` zet actie `status_gewijzigd` alléén als de status het enige
     * gewijzigde veld was. Een risico dat tegelijk werd beoordeeld draagt actie
     * `gewijzigd` — filteren op de actie zou die overgang missen.
     */
    public function test_een_overgang_telt_ook_als_de_actie_gewijzigd_is(): void
    {
        $risico = Risico::factory()->beoordeeld(3, 4)->create();

        // Twee overgangen, één met een gebundelde wijziging.
        $this->risicoTrail($risico,
            ['status' => 'geidentificeerd', 'risicoscore' => null],
            ['status' => 'beoordeeld', 'risicoscore' => 12],
            actie: 'gewijzigd');
        $this->risicoTrail($risico,
            ['status' => 'behandelplan_opgesteld'],
            ['status' => 'gemitigeerd'],
            actie: 'status_gewijzigd');

        $this->artisan('isms:meet-kpis');

        $meting = $this->meting('behandelplannen_afgerond');
        $this->assertSame(1, $meting->teller);
        $this->assertSame(2, $meting->noemer, 'Beide overgangen horen in de noemer.');
    }

    public function test_nieuwe_risicos_telt_binnen_de_periode(): void
    {
        Carbon::setTestNow('2026-06-15');
        Risico::factory()->create();

        // Eerste meting: geen ondergrens, dus dit ene risico telt mee.
        $this->artisan('isms:meet-kpis');
        $eerste = $this->meting('nieuwe_risicos');
        $this->assertSame(1, $eerste->teller);
        $this->assertSame(1, $eerste->noemer);
        $this->assertNull($eerste->periode_van, 'Het eerste venster heeft geen ondergrens.');
        $this->assertSame('2026-06-15', $eerste->periode_tot->toDateString());

        // Volgende maand twee erbij: het venster begint waar het vorige eindigde.
        Carbon::setTestNow('2026-07-15');
        Risico::factory()->count(2)->create();
        $this->artisan('isms:meet-kpis');

        $laatste = KpiDefinitie::where('sleutel', 'nieuwe_risicos')->firstOrFail()
            ->metingen()->latest('gemeten_op')->firstOrFail();

        $this->assertSame(2, $laatste->teller, 'Alleen wat ná het vorige venster kwam.');
        $this->assertSame('2026-06-15', $laatste->periode_van->toDateString());

        Carbon::setTestNow();
    }

    /**
     * Bij een toestandsmeting kost een gemiste run één stip; bij een
     * gebeurtenismeting zouden de gebeurtenissen uit die maand permanent buiten
     * élke meting vallen. Het venster herstelt zichzelf (12g §3).
     */
    public function test_het_venster_herstelt_zich_na_een_overgeslagen_maand(): void
    {
        Carbon::setTestNow('2026-06-15');
        $this->artisan('isms:meet-kpis');

        // Juli wordt overgeslagen; er ontstaan wél risico's.
        Carbon::setTestNow('2026-07-10');
        Risico::factory()->count(3)->create();

        Carbon::setTestNow('2026-08-15');
        $this->artisan('isms:meet-kpis');

        $laatste = KpiDefinitie::where('sleutel', 'nieuwe_risicos')->firstOrFail()
            ->metingen()->latest('gemeten_op')->firstOrFail();

        $this->assertSame(3, $laatste->teller, 'De juli-risico\'s horen alsnog geteld te worden.');
        $this->assertSame('2026-06-15', $laatste->periode_van->toDateString());

        Carbon::setTestNow();
    }

    public function test_een_toestandsmeting_laat_de_periodekolommen_leeg(): void
    {
        $this->soaRegel(true);

        $this->artisan('isms:meet-kpis');

        $toestand = $this->meting('soa_beoordeeld');
        $this->assertNull($toestand->periode_van);
        $this->assertNull($toestand->periode_tot);
        $this->assertFalse($toestand->isGebeurtenis());

        $this->assertTrue($this->meting('nieuwe_risicos')->isGebeurtenis());
    }

    // --- De meetbron-registry (implementatie/12e §4) --------------------------

    /**
     * De kern van 12e §1: een meetbron die uit de code verdwijnt terwijl er nog
     * definities aan hangen, moet luidruchtig zijn. Vóór dit plan was dat niet
     * te onderscheiden van "geen populatie" — geen melding, exitcode 0, en een
     * KPI die nooit meet ziet er precies zo uit als één die nog historie opbouwt.
     */
    public function test_onbekende_meetbron_waarschuwt_en_geeft_een_niet_nul_exitcode(): void
    {
        KpiDefinitie::create([
            'sleutel' => 'weggerefactorde_kpi',
            'meetbron' => 'bestaat_niet_meer',
            'naam' => 'Verweesde KPI',
            'fase' => 'check',
            'eenheid' => 'ratio',
            'richting' => 'omhoog',
            'berekeningswijze' => 'Ooit iets, nu niets.',
            'definitie_versie' => 1,
            'actief' => true,
        ]);

        $this->artisan('isms:meet-kpis')
            ->expectsOutputToContain('bestaat_niet_meer')
            ->assertFailed();

        $this->assertNull($this->meting('weggerefactorde_kpi'));
    }

    /** Eén kapotte definitie mag de maandelijkse run niet stilleggen. */
    public function test_onbekende_meetbron_blokkeert_de_overige_metingen_niet(): void
    {
        $this->soaRegel(true);

        KpiDefinitie::create([
            'sleutel' => 'weggerefactorde_kpi',
            'meetbron' => 'bestaat_niet_meer',
            'naam' => 'Verweesde KPI',
            'fase' => 'check',
            'eenheid' => 'ratio',
            'richting' => 'omhoog',
            'berekeningswijze' => 'Ooit iets, nu niets.',
            'definitie_versie' => 1,
            'actief' => true,
        ]);

        $this->artisan('isms:meet-kpis')->assertFailed();

        $this->assertNotNull($this->meting('soa_beoordeeld'));
    }

    public function test_elke_geseede_meetbron_bestaat_in_de_registry(): void
    {
        $meetbronnen = KpiDefinitie::whereNotNull('meetbron')->pluck('meetbron', 'sleutel');

        $this->assertNotEmpty($meetbronnen);

        foreach ($meetbronnen as $sleutel => $meetbron) {
            $this->assertTrue(
                Meetbronnen::bestaat($meetbron),
                "KPI '{$sleutel}' verwijst naar onbekende meetbron '{$meetbron}'."
            );
        }
    }

    /**
     * Een handmatige KPI wordt door het commando met rust gelaten: geen
     * waarschuwing (dat is geen fout) en geen lege meetrij.
     */
    public function test_handmatige_kpi_wordt_stil_overgeslagen(): void
    {
        KpiDefinitie::create([
            'sleutel' => 'phishing_klikratio',
            'meetbron' => null,
            'naam' => 'Klikratio phishingsimulatie',
            'fase' => 'check',
            'eenheid' => 'ratio',
            'richting' => 'omhoog',
            'berekeningswijze' => 'Handmatig ingevoerd uit de simulatietool.',
            'definitie_versie' => 1,
            'actief' => true,
        ]);

        $this->artisan('isms:meet-kpis')->assertSuccessful();

        $this->assertNull($this->meting('phishing_klikratio'));
    }

    /** De keuzelijst voor het beheerscherm dekt precies de berekenbare bronnen. */
    public function test_keuzelijst_en_voorstel_beschrijven_elke_meetbron(): void
    {
        foreach (Meetbronnen::keuzelijst() as $meetbron => $label) {
            $this->assertNotSame('', $label);

            $voorstel = Meetbronnen::voorstel($meetbron);
            $this->assertContains($voorstel['eenheid'], ['ratio', 'dagen', 'aantal']);
            $this->assertContains($voorstel['richting'], ['omhoog', 'omlaag']);
            $this->assertNotSame('', $voorstel['berekeningswijze']);

            // Berekenbaar op een lege database, zonder uitzondering.
            $this->assertIsArray(Meetbronnen::bereken($meetbron));
        }

        $this->assertNull(Meetbronnen::voorstel('bestaat_niet'));
        $this->assertNull(Meetbronnen::bereken('bestaat_niet'));
    }
}
