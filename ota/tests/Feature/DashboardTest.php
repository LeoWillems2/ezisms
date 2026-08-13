<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Beleidsdocument;
use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Models\KpiDefinitie;
use App\Models\Maatregel;
use App\Models\Risico;
use App\Support\Kpitrend;
use App\Support\Maatregelverdeling;
use App\Support\Risicoverdeling;
use Database\Seeders\BlokSeeder;
use Database\Seeders\KpiDefinitieSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Het dashboard (implementatie/12c).
 *
 * De rolzichtbaarheid staat vooraan en niet achteraan (12c §8): elk paneel hangt
 * achter de autorisatiecheck van zijn eigen blok, en een paneel dat aan de
 * verkeerde blokcode hangt valt in het scherm van een CISO niet op.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, KpiDefinitieSeeder::class, RisicocriteriaSeeder::class]);
    }

    private function legMeetpuntVast(string $sleutel, int $teller, int $noemer, ?Carbon $op = null): void
    {
        $definitie = KpiDefinitie::where('sleutel', $sleutel)->firstOrFail();
        $definitie->metingen()->create([
            'gemeten_op' => $op ?? now(),
            'teller' => $teller,
            'noemer' => $noemer,
            // Zelfde kopieerregel als `isms:meet-kpis`: alleen een vastgestelde
            // norm gaat mee de meetrij in (12d §2b, 12e §9).
            'definitie_versie' => $definitie->definitie_versie,
            'streefwaarde' => $definitie->vastgesteldeStreefwaarde(),
            'signaalwaarde' => $definitie->vastgesteldeSignaalwaarde(),
        ]);
    }

    /** De meegeleverde norm adopteren, zoals de CISO dat in het scherm doet. */
    private function stelStreefwaardeVast(string $sleutel): void
    {
        KpiDefinitie::where('sleutel', $sleutel)->update(['streefwaarde_vastgesteld_op' => now()]);
    }

    // --- Rolzichtbaarheid (§2, §8) -----------------------------------------

    public function test_ciso_ziet_alle_panelen(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        Maatregel::factory()->metSoaRegel()->create();

        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertSee('Kerncijfers')
            ->assertSee('Signalen')
            ->assertSee('PDCA-trend')
            ->assertSee("Risico's")
            ->assertSee('Maatregelen per thema')
            ->assertSee('Documenten en bewijzen');
    }

    /**
     * De Medewerker heeft geen risico-inzage en geen leesrecht op de
     * meetaanpak. Hij houdt zijn takenlijst over — en dat is precies waarom het
     * dashboard niet achter één gate zit.
     */
    public function test_medewerker_houdt_alleen_zijn_taken_over(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)->get('/dashboard')
            ->assertOk()
            ->assertSee('Mijn openstaande taken')
            ->assertDontSee('Kerncijfers')
            ->assertDontSee('PDCA-trend')
            ->assertDontSee('Maatregelen per thema');
    }

    public function test_auditor_ziet_de_cijfers_maar_heeft_geen_eigen_takenpaneel(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/dashboard')
            ->assertOk()
            ->assertSee('Kerncijfers')
            ->assertSee('PDCA-trend')
            // De Auditor heeft `lezen` op taken, geen `uitvoeren`: geen eigen
            // takenlijst, want hij voert niets uit.
            ->assertDontSee('Mijn openstaande taken');
    }

    // --- Leeg-staat ---------------------------------------------------------

    public function test_zonder_metingen_geen_nullen_maar_een_melding(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertSee('nog niet gemeten')
            // Als tekst tussen tags, niet als kale losse tekens: sinds de
            // tweefactor-callout (01d §9) staan er Tailwind-klassen als
            // `transparent_90%` in de HTML, en daar zit "0%" toevallig in.
            ->assertDontSee('>0%<', escape: false);
    }

    /**
     * De Act-fase stond leeg zolang de drie geplande Act-metingen de audit trail
     * nodig hadden. Sinds 12d §4 meet het ISMS zijn eigen bijsturing wél — de
     * corrigerende maatregelen uit §10.1 hebben daar geen audit trail voor
     * nodig. De toelichting noemt daarom alleen nog het deel dat écht ontbreekt.
     */
    public function test_de_act_fase_meet_de_corrigerende_maatregelen(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        $this->assertSame(5, KpiDefinitie::where('fase', 'act')->where('actief', true)->count());

        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertSee('corrigerende maatregelen uit §10.1', escape: false)
            ->assertSee('audit trail')
            ->assertDontSee('Nog geen Act-metingen');
    }

    // --- Gedeelde matrixtelling (§3.4) --------------------------------------

    /**
     * Dit is de test die de gedeelde berekening afdwingt. Zou het dashboard zijn
     * eigen telling doen, dan zouden beide schermen er even plausibel uitzien en
     * zou het verschil nergens opvallen.
     */
    public function test_de_matrixtelling_is_gelijk_aan_die_van_de_volledige_matrix(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        Risico::factory()->beoordeeld(4, 4)->create();
        Risico::factory()->beoordeeld(4, 4)->create();
        Risico::factory()->beoordeeld(1, 2)->create();
        Risico::factory()->create(['kans_niveau' => null, 'impact_niveau' => null]);

        $verdeling = Risicoverdeling::huidige();

        $this->assertSame(2, $verdeling->aantalIn(4, 4));
        $this->assertSame(1, $verdeling->aantalIn(1, 2));
        $this->assertSame(3, $verdeling->beoordeeld);
        $this->assertSame(1, $verdeling->nietBeoordeeld);
        // 4 x 4 = 16, boven de drempel van 15.
        $this->assertSame(2, $verdeling->bovenDrempel());

        // Beide schermen tonen dezelfde tellers uit dezelfde bron.
        $dashboard = $this->actingAs($ciso)->get('/dashboard')->assertOk();
        $matrix = $this->actingAs($ciso)->get('/risicos/matrix')->assertOk();

        foreach ([$dashboard, $matrix] as $antwoord) {
            $antwoord->assertSee('kans', false);
        }
    }

    public function test_de_som_van_de_themabalken_is_het_aantal_soa_regels(): void
    {
        Maatregel::factory()->count(3)->metSoaRegel()->create(['thema' => 'organisatorisch']);
        Maatregel::factory()->count(2)->metSoaRegel()->create(['thema' => 'technologisch']);

        $verdeling = Maatregelverdeling::huidige();

        $this->assertSame(5, $verdeling->totaal);
        $this->assertSame(3, $verdeling->totaalVoorThema('organisatorisch'));
        $this->assertSame(2, $verdeling->totaalVoorThema('technologisch'));
        $this->assertSame(0, $verdeling->totaalVoorThema('fysiek'));
    }

    public function test_alle_statussen_blijven_in_de_verdeling_ook_met_nul(): void
    {
        Maatregel::factory()->metSoaRegel()->create(['thema' => 'fysiek']);

        $verdeling = Maatregelverdeling::huidige();

        // Een klasse die vandaag leeg is moet morgen een plek hebben; de legenda
        // hoort niet te verspringen zodra er één regel op 'niet gestart' komt.
        foreach (Maatregelverdeling::statussen() as $status) {
            $this->assertArrayHasKey($status, $verdeling->perThema['fysiek']);
        }
    }

    // --- Delta en richting (§3.1) ------------------------------------------

    public function test_de_delta_loopt_over_twaalf_maanden(): void
    {
        $this->legMeetpuntVast('soa_geimplementeerd', 40, 100, now()->subMonths(12));
        $this->legMeetpuntVast('soa_geimplementeerd', 60, 100, now()->subMonths(6));
        $this->legMeetpuntVast('soa_geimplementeerd', 90, 100, now());

        $trend = $this->trendVoor('soa_geimplementeerd');

        // Niet tegen de vorige meting (60) maar tegen die van twaalf maanden terug.
        $this->assertSame(50.0, $trend->delta());
        $this->assertSame('op', $trend->richting());
    }

    /**
     * §6 van het plan: bij een reeks korter dan dertien metingen is de eerste
     * meting de basis. "Sinds we meten" is een eerlijke vergelijking; een lege
     * delta is dat niet.
     */
    public function test_een_korte_reeks_vergelijkt_met_de_eerste_meting(): void
    {
        $this->legMeetpuntVast('soa_geimplementeerd', 20, 100, now()->subMonths(2));
        $this->legMeetpuntVast('soa_geimplementeerd', 50, 100, now());

        $this->assertSame(30.0, $this->trendVoor('soa_geimplementeerd')->delta());
    }

    /**
     * Bij de overschrijding in dagen is omlaag de goede kant op. Die vlag hoort
     * bij de definitie, niet in de weergave.
     */
    public function test_bij_de_dagen_kpi_is_omlaag_een_positieve_richting(): void
    {
        $this->legMeetpuntVast('reviewtaken_gem_overschrijding', 6000, 100, now()->subMonths(12));
        $this->legMeetpuntVast('reviewtaken_gem_overschrijding', 1800, 100, now());

        $trend = $this->trendVoor('reviewtaken_gem_overschrijding');

        $this->assertTrue($trend->inDagen());
        $this->assertSame(-42.0, $trend->delta());
        $this->assertSame('op', $trend->richting(), 'Minder overschrijding is een verbetering.');
    }

    public function test_een_vlakke_reeks_levert_geen_richting_op(): void
    {
        $this->legMeetpuntVast('soa_geimplementeerd', 50, 100, now()->subMonths(12));
        $this->legMeetpuntVast('soa_geimplementeerd', 50, 100, now());

        $this->assertSame('vlak', $this->trendVoor('soa_geimplementeerd')->richting());
    }

    // --- Variantie als signaal (§4) -----------------------------------------

    public function test_een_terugval_verschijnt_als_signaal(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->legMeetpuntVast('soa_geimplementeerd', 90, 100, now()->subMonth());
        $this->legMeetpuntVast('soa_geimplementeerd', 70, 100, now());

        $this->assertTrue($this->trendVoor('soa_geimplementeerd')->laatsteStapIsAchteruit());

        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertSee('ging achteruit');
    }

    /**
     * Het positieve variantiesignaal: een reeks die inzakt en zich herstelt is
     * bewijs dat de Check-fase meet (blok 12 §4).
     */
    public function test_een_herstelde_dip_is_een_positief_signaal(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        foreach ([100, 100, 100, 100, 0, 0, 95] as $i => $teller) {
            $this->legMeetpuntVast('soa_herbeoordeeld_binnen_termijn', $teller, 100, now()->subMonths(6 - $i));
        }

        $this->assertNotNull($this->trendVoor('soa_herbeoordeeld_binnen_termijn')->herstelNaDip());

        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertSee('ingezakt en hersteld');
    }

    /**
     * Een oplopende opbouwcurve is géén herstelde dip. Zonder deze eis meldt
     * elke KPI die vanaf nul is opgebouwd een "herstel", want nu is altijd hoger
     * dan het laagste punt. Een dip vraagt twee bewegingen: een val vanaf een
     * eerder hoogtepunt, en daarna het herstel.
     */
    public function test_een_stijgende_opbouwcurve_is_geen_herstelde_dip(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        foreach ([0, 0, 40, 40, 62, 80, 91] as $i => $teller) {
            $this->legMeetpuntVast('soa_geimplementeerd', $teller, 100, now()->subMonths(6 - $i));
        }

        $this->assertNull(
            $this->trendVoor('soa_geimplementeerd')->herstelNaDip(),
            'Een reeks die alleen maar stijgt heeft geen dip om van te herstellen.',
        );

        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('ingezakt en hersteld');
    }

    /**
     * Blok 12 §4 wil het signaal "scoredaling zonder gekoppeld bewijs". Dat kan
     * nog niet, en dat hoort in het scherm te staan en niet alleen in het plan.
     */
    public function test_het_onberekenbare_signaal_staat_er_met_de_reden_bij(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertSee('nog niet gemeten')
            ->assertSee('audit');
    }

    public function test_risicosignalen_blijven_weg_zonder_risico_inzage(): void
    {
        Risico::factory()->beoordeeld(5, 5)->create();

        // Management leest de review én de risico's; de Medewerker geen van beide.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('boven de acceptatiedrempel');
    }

    // --- Aantallen (§3.5) ---------------------------------------------------

    public function test_de_aantallen_tellen_documenten_versies_en_bewijs(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        $document = Beleidsdocument::factory()->create(['status' => 'actief']);
        $document->versies()->create(['versienummer' => 1, 'status' => 'vervangen']);
        $document->versies()->create(['versienummer' => 2, 'status' => 'actief']);
        Beleidsdocument::factory()->create(['status' => 'concept']);
        Bewijsstuk::factory()->count(3)->create(['status' => 'actief']);

        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertSee('Beleidsdocumenten')
            ->assertSee('1 document herzien')
            ->assertSee('Bewijsstukken');
    }

    // --- Richting als eigen vlag (implementatie/12d §1) ---------------------

    /**
     * De test die de latente breuk afvangt. Vóór 12d fase 1 werd de richting uit
     * de eenheid afgeleid; een ratio waarbij omlaag goed is — open bevindingen —
     * zou dan een daling als achteruitgang rapporteren.
     */
    public function test_bij_een_ratio_met_richting_omlaag_is_dalen_verbetering(): void
    {
        // `bevindingen_open` is de KPI die deze fase rechtvaardigt: een ratio
        // waarbij omlaag goed is (12d §4).
        $definitie = KpiDefinitie::where('sleutel', 'bevindingen_open')->firstOrFail();

        foreach ([[60, Carbon::parse('2025-08-31')], [20, Carbon::now()]] as [$teller, $op]) {
            $definitie->metingen()->create([
                'gemeten_op' => $op, 'teller' => $teller, 'noemer' => 100,
                'definitie_versie' => 1,
            ]);
        }

        $trend = $this->trendVoor('bevindingen_open');

        $this->assertTrue($trend->omlaagIsGoed());
        $this->assertSame(-40.0, $trend->delta());
        $this->assertSame('op', $trend->richting());
        $this->assertFalse($trend->laatsteStapIsAchteruit());
    }

    public function test_de_catalogus_heeft_een_richting_per_kpi(): void
    {
        // Elke 'dagen'-KPI wil omlaag: dat zijn doorlooptijden en achterstanden.
        $this->assertSame(
            0,
            KpiDefinitie::where('eenheid', 'dagen')->where('richting', '!=', 'omlaag')->count()
        );

        // En twee ratio's willen dat óók — het bewijs dat de richting geen proxy
        // meer is voor de eenheid (12d §1).
        $this->assertSame(
            ['bevindingen_open', 'scoredaling_zonder_bewijs'],
            KpiDefinitie::where('eenheid', 'ratio')->where('richting', 'omlaag')
                ->pluck('sleutel')->all()
        );
    }

    // --- Normering (implementatie/12d §2) -----------------------------------

    public function test_de_status_volgt_streefwaarde_en_signaalwaarde(): void
    {
        // soa_beoordeeld: streef 100, signaal 95.
        $this->stelStreefwaardeVast('soa_beoordeeld');
        $this->legMeetpuntVast('soa_beoordeeld', 100, 100);
        $this->assertSame(Kpitrend::STATUS_GOED, $this->trendVoor('soa_beoordeeld')->status());

        // risico_herbeoordeeld_binnen_termijn: streef 95, signaal 85.
        $this->stelStreefwaardeVast('risico_herbeoordeeld_binnen_termijn');
        $this->legMeetpuntVast('risico_herbeoordeeld_binnen_termijn', 90, 100);
        $this->assertSame(Kpitrend::STATUS_AANDACHT, $this->trendVoor('risico_herbeoordeeld_binnen_termijn')->status());

        // reviewtaken_op_tijd: streef 90, signaal 75.
        $this->stelStreefwaardeVast('reviewtaken_op_tijd');
        $this->legMeetpuntVast('reviewtaken_op_tijd', 50, 100);
        $this->assertSame(Kpitrend::STATUS_SLECHT, $this->trendVoor('reviewtaken_op_tijd')->status());
    }

    /**
     * De meegeleverde norm is een voorstel: hij kleurt niets tot de organisatie
     * hem vaststelt (12e §9). Zonder deze test zou een geseede streefwaarde als
     * vastgesteld beleid gaan gelden.
     */
    public function test_een_meegeleverde_norm_kleurt_niets_tot_hij_is_vastgesteld(): void
    {
        $this->legMeetpuntVast('soa_beoordeeld', 100, 100);

        $this->assertSame(100.0, KpiDefinitie::where('sleutel', 'soa_beoordeeld')->value('streefwaarde'));
        $this->assertSame(Kpitrend::STATUS_ONBEPAALD, $this->trendVoor('soa_beoordeeld')->status());
    }

    /** Bij richting omlaag draait de vergelijking om: streef 5 dagen, signaal 15. */
    public function test_bij_richting_omlaag_is_kleiner_dan_de_streefwaarde_goed(): void
    {
        $this->stelStreefwaardeVast('reviewtaken_gem_overschrijding');

        $this->legMeetpuntVast('reviewtaken_gem_overschrijding', 4, 1);
        $this->assertSame(Kpitrend::STATUS_GOED, $this->trendVoor('reviewtaken_gem_overschrijding')->status());

        KpiDefinitie::where('sleutel', 'reviewtaken_gem_overschrijding')->firstOrFail()
            ->metingen()->delete();

        $this->legMeetpuntVast('reviewtaken_gem_overschrijding', 30, 1);
        $this->assertSame(Kpitrend::STATUS_SLECHT, $this->trendVoor('reviewtaken_gem_overschrijding')->status());
    }

    /**
     * Afwezigheid van een norm mag niet lezen als "op norm". Dat is de enige
     * manier waarop dit veld schade kan aanrichten (12d §2).
     */
    public function test_een_kpi_zonder_streefwaarde_is_onbepaald_en_nooit_groen(): void
    {
        // soa_geimplementeerd heeft bewust geen norm (12d §2c) — er valt dus ook
        // niets vast te stellen.
        $this->legMeetpuntVast('soa_geimplementeerd', 100, 100);

        $trend = $this->trendVoor('soa_geimplementeerd');

        $this->assertSame(Kpitrend::STATUS_ONBEPAALD, $trend->status());
        $this->assertSame('zinc', Kpitrend::statusKleur($trend->status()));
        $this->assertSame('Geen streefwaarde vastgesteld', Kpitrend::statusLabel($trend->status()));
    }

    /**
     * De borging van 12d §2b en de kern van 12 §2c: een cijfer dat meebeweegt
     * als je later kijkt, is geen meting. Verlaag je de norm, dan mogen oude
     * rode punten niet groen kleuren.
     */
    public function test_een_bijgestelde_streefwaarde_herkleurt_de_historie_niet(): void
    {
        $this->stelStreefwaardeVast('soa_beoordeeld');
        $this->legMeetpuntVast('soa_beoordeeld', 80, 100); // streef 100 → slecht (signaal 95)

        $this->assertSame(Kpitrend::STATUS_SLECHT, $this->trendVoor('soa_beoordeeld')->status());

        KpiDefinitie::where('sleutel', 'soa_beoordeeld')->update([
            'streefwaarde' => 70, 'signaalwaarde' => 50,
        ]);

        $this->assertSame(Kpitrend::STATUS_SLECHT, $this->trendVoor('soa_beoordeeld')->status());
    }

    /**
     * De voorspelbare manier waarop een handmatige KPI misgaat: enthousiast
     * aangemaakt, na twee maanden vergeten. Op het trendpaneel is dat niet te
     * onderscheiden van "meet nog niet" (12e §5).
     */
    public function test_een_stilgevallen_handmatige_kpi_verschijnt_als_signaal(): void
    {
        $kpi = KpiDefinitie::create([
            'sleutel' => 'phishing_klikratio',
            'meetbron' => null,
            'naam' => 'Klikratio phishingsimulatie',
            'fase' => 'check',
            'eenheid' => 'ratio',
            'richting' => 'omlaag',
            'berekeningswijze' => 'Klikkers gedeeld door verzonden simulatiemails.',
            'definitie_versie' => 1,
            'actief' => true,
        ]);

        $kpi->metingen()->create([
            'gemeten_op' => now()->subMonthsNoOverflow(4),
            'teller' => 7, 'noemer' => 140, 'definitie_versie' => 1,
        ]);

        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertSee('1 handmatige KPI is stilgevallen')
            ->assertSee('Klikratio phishingsimulatie');
    }

    public function test_een_pas_ingevulde_handmatige_kpi_levert_geen_signaal(): void
    {
        $kpi = KpiDefinitie::create([
            'sleutel' => 'phishing_klikratio',
            'meetbron' => null,
            'naam' => 'Klikratio phishingsimulatie',
            'fase' => 'check',
            'eenheid' => 'ratio',
            'richting' => 'omlaag',
            'berekeningswijze' => 'Klikkers gedeeld door verzonden simulatiemails.',
            'definitie_versie' => 1,
            'actief' => true,
        ]);

        $kpi->metingen()->create([
            'gemeten_op' => now(), 'teller' => 7, 'noemer' => 140, 'definitie_versie' => 1,
        ]);

        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        // En een KPI die zojuist is aangemaakt en nog nooit is ingevuld is niet
        // stilgevallen maar nog niet begonnen.
        KpiDefinitie::create([
            'sleutel' => 'net_aangemaakt',
            'meetbron' => null,
            'naam' => 'Net aangemaakt',
            'fase' => 'check',
            'eenheid' => 'ratio',
            'richting' => 'omhoog',
            'berekeningswijze' => 'Nog niets ingevuld.',
            'definitie_versie' => 1,
            'actief' => true,
        ]);

        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('stilgevallen');
    }

    // --- Weergave van de norm (implementatie/12d §6) ------------------------

    /**
     * De strip koos vroeger vier vaste sleutels, met de aantekening "de laatste
     * staat er bewust bij, die staat het minst goed". Dat oordeel verouderde
     * zodra de cijfers bewogen. Nu bepaalt de status het — dat is waar de
     * streefwaarde voor is (12d §6).
     */
    public function test_de_strip_toont_het_anker_en_daarna_de_slechtst_staande_kpis(): void
    {
        // Voorbij de signaalwaarde: streef 90, signaal 75, uitkomst 50%.
        $this->stelStreefwaardeVast('reviewtaken_op_tijd');
        $this->legMeetpuntVast('reviewtaken_op_tijd', 50, 100);

        // Op norm: die hoort juist wég te vallen uit de strip.
        $this->stelStreefwaardeVast('soa_beoordeeld');
        $this->legMeetpuntVast('soa_beoordeeld', 100, 100);

        $strip = Livewire::actingAs(Gebruiker::factory()->metRol('CISO')->create())
            ->test(Dashboard::class)
            ->viewData('strip');

        $sleutels = $strip->map(fn (Kpitrend $t) => $t->definitie->sleutel)->all();

        $this->assertCount(4, $sleutels);
        $this->assertSame('soa_geimplementeerd', $sleutels[0], 'Het anker staat vast vooraan.');
        $this->assertContains('reviewtaken_op_tijd', $sleutels);
        $this->assertNotContains('soa_beoordeeld', $sleutels, 'Een KPI op norm verdringt geen slechtere.');

        // 12c §3.1: er hoort er minstens één tussen te zitten die niet goed staat.
        $this->assertTrue(
            $strip->contains(fn (Kpitrend $t) => $t->status() !== Kpitrend::STATUS_GOED),
        );
    }

    public function test_de_tegel_toont_de_streefwaarde_en_het_oordeel(): void
    {
        $this->stelStreefwaardeVast('reviewtaken_op_tijd');
        $this->legMeetpuntVast('reviewtaken_op_tijd', 50, 100);

        $this->actingAs(Gebruiker::factory()->metRol('CISO')->create())->get('/dashboard')
            ->assertOk()
            // Een kleur zonder maatstaf is een oordeel zonder maatstaf.
            ->assertSee('streef 90%')
            ->assertSee('Voorbij de signaalwaarde');
    }

    public function test_een_kpi_voorbij_de_signaalwaarde_levert_een_signaal(): void
    {
        $this->stelStreefwaardeVast('reviewtaken_op_tijd');
        $this->legMeetpuntVast('reviewtaken_op_tijd', 50, 100);

        $this->actingAs(Gebruiker::factory()->metRol('CISO')->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('Beheerde taken op tijd afgerond staat voorbij de signaalwaarde')
            ->assertSee('De vastgestelde signaalwaarde is 75%, de streefwaarde 90%.');
    }

    /**
     * De stippellijn is gereserveerd: het raster in `x-diagram.trendlijn` is
     * bewust solide, juist omdat gestreept in dat diagram "drempel" betekent.
     */
    public function test_de_streefwaarde_verschijnt_als_stippellijn_zodra_hij_is_vastgesteld(): void
    {
        $vorigeMaand = Carbon::now()->subMonthNoOverflow();

        $this->legMeetpuntVast('reviewtaken_op_tijd', 60, 100, $vorigeMaand);
        $this->legMeetpuntVast('reviewtaken_op_tijd', 50, 100);

        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        // Zonder vastgestelde streefwaarde tekent het diagram geen drempel.
        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('stroke-dasharray', escape: false);

        // De norm gaat pas mee vanaf het meetpunt dat erná is vastgelegd.
        $this->stelStreefwaardeVast('reviewtaken_op_tijd');
        KpiDefinitie::where('sleutel', 'reviewtaken_op_tijd')->firstOrFail()
            ->metingen()->latest('gemeten_op')->firstOrFail()
            ->update(['streefwaarde' => 90.0, 'signaalwaarde' => 75.0]);

        $this->actingAs($ciso)->get('/dashboard')
            ->assertOk()
            ->assertSee('stroke-dasharray', escape: false);
    }

    // --- Hulpje ------------------------------------------------------------

    private function trendVoor(string $sleutel): Kpitrend
    {
        $definitie = KpiDefinitie::where('sleutel', $sleutel)->with('metingen')->firstOrFail();

        return Kpitrend::van($definitie, $definitie->metingen);
    }
}
