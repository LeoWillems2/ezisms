<?php

namespace Tests\Feature;

use App\Livewire\MeetaanpakOverzicht;
use App\Models\AuditLogregel;
use App\Models\Gebruiker;
use App\Models\KpiDefinitie;
use Database\Seeders\BlokSeeder;
use Database\Seeders\KpiDefinitieSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class MeetaanpakTest extends TestCase
{
    use RefreshDatabase;

    /**
     * De meegeleverde KPI's waar dit bestand op leunt. `phishing_klikratio`
     * hoort er ook bij, maar die maakt `handmatigeKpi()` zelf aan.
     */
    private const GEBRUIKTE_KPIS = [
        'soa_beoordeeld',
        'soa_geimplementeerd',
        'reviewtaken_gem_overschrijding',
    ];

    /**
     * Elke aanroep in dit bestand rendert het hele `/meetaanpak`-scherm, en dat
     * schaalt met de catalogus: negentien definities kosten 0,245 s per render,
     * twee 0,079 s (implementatie/00f §3a). Vandaar: gewoon seeden — de seeder
     * zelf kost niets — en daarna terugbrengen tot wat hier gebruikt wordt.
     * Dat de volledige catalogus rendert, blijft gedekt door
     * `test_auditor_ziet_de_catalogus_met_berekeningswijze`.
     *
     * Het weghalen gaat langs de query builder en dus buiten de modelgebeurtenissen
     * om: er komen geen negentien verwijderregels in de audit trail te staan.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, KpiDefinitieSeeder::class]);

        KpiDefinitie::whereNotIn('sleutel', self::GEBRUIKTE_KPIS)->delete();
    }

    /** De seeder is idempotent (`firstOrCreate`), dus terugzetten is één aanroep. */
    private function volledigeCatalogus(): void
    {
        $this->seed(KpiDefinitieSeeder::class);
    }

    private function legMeetpuntVast(string $sleutel, int $teller, int $noemer): void
    {
        $definitie = KpiDefinitie::where('sleutel', $sleutel)->firstOrFail();
        $definitie->metingen()->create([
            'gemeten_op' => now(),
            'teller' => $teller,
            'noemer' => $noemer,
            'definitie_versie' => $definitie->definitie_versie,
        ]);
    }

    /**
     * De enige test hier die de volledige catalogus terugzet: de rest draait op
     * drie KPI's. Wat één KPI toont, toetsen de tests hieronder; dat het scherm
     * ze *allemaal* toont, staat hier.
     */
    public function test_auditor_ziet_de_catalogus_met_berekeningswijze(): void
    {
        $this->volledigeCatalogus();
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $antwoord = $this->actingAs($auditor)->get('/meetaanpak')
            ->assertOk()
            ->assertSee("KPI's")
            ->assertSee('meetaanpak')
            ->assertSee('gedeeld door het totaal aantal Annex A-maatregelen'); // stuk berekeningswijze

        $namen = KpiDefinitie::orderBy('id')->pluck('naam');
        $this->assertCount(23, $namen);

        foreach ($namen as $naam) {
            $antwoord->assertSee($naam);
        }
    }

    public function test_leeg_zonder_metingen(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/meetaanpak')
            ->assertOk()
            ->assertSee('Nog geen meetpunten');
    }

    public function test_ratio_meetpunt_toont_afgeleid_percentage(): void
    {
        $this->legMeetpuntVast('soa_beoordeeld', 61, 90); // 67.8%

        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/meetaanpak')
            ->assertOk()
            ->assertSee('61 van 90')
            ->assertSee('67.8%');
    }

    public function test_dagen_meetpunt_toont_afgeleid_gemiddelde(): void
    {
        $this->legMeetpuntVast('reviewtaken_gem_overschrijding', 5, 2); // 2.5 dagen gem.

        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/meetaanpak')
            ->assertOk()
            ->assertSee('2.5 dagen');
    }

    /**
     * Een KPI met twee jaar maandmetingen duwde elke volgende KPI van het
     * scherm af. Alleen het jongste meetpunt staat open; de rest zit eronder.
     */
    public function test_alleen_het_jongste_meetpunt_staat_open(): void
    {
        $definitie = KpiDefinitie::where('sleutel', 'soa_beoordeeld')->firstOrFail();

        foreach ([['2026-06-30', 40], ['2026-07-31', 55], ['2026-08-02', 61]] as [$datum, $teller]) {
            $definitie->metingen()->create([
                'gemeten_op' => $datum, 'teller' => $teller, 'noemer' => 90,
                'definitie_versie' => 1,
            ]);
        }

        $antwoord = $this->actingAs(Gebruiker::factory()->metRol('Auditor')->create())
            ->get('/meetaanpak')
            ->assertOk();

        // Alle drie staan in de HTML — inklappen is geen weglaten, en Ctrl+F
        // hoort de historie te vinden.
        foreach (['30-06-2026', '31-07-2026', '02-08-2026'] as $datum) {
            $antwoord->assertSee($datum);
        }

        $antwoord->assertSee('Toon 2 eerdere meetpunten')
            ->assertSee('style="display: none"', escape: false);
    }

    public function test_bij_een_enkel_meetpunt_valt_de_openklapknop_weg(): void
    {
        $this->legMeetpuntVast('soa_beoordeeld', 61, 90);

        $this->actingAs(Gebruiker::factory()->metRol('Auditor')->create())
            ->get('/meetaanpak')
            ->assertOk()
            ->assertSee('61 van 90')
            ->assertDontSee('eerdere meetpunten')
            ->assertDontSee('eerder meetpunt');
    }

    // --- Beheer van de catalogus (implementatie/12e §6 en §7) ----------------

    private function ciso(): Gebruiker
    {
        return Gebruiker::factory()->metRol('CISO')->create();
    }

    private function definitie(string $sleutel): KpiDefinitie
    {
        return KpiDefinitie::where('sleutel', $sleutel)->firstOrFail();
    }

    public function test_ciso_maakt_een_handmatige_kpi_aan(): void
    {
        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('nieuweKpi')
            ->set('naam', 'Klikratio phishingsimulatie')
            ->set('meetbron', '')          // handmatig
            ->set([
                'fase' => 'check',
                'eenheid' => 'ratio',
                'richting' => 'omlaag',
                'berekeningswijze' => 'Klikkers gedeeld door verzonden simulatiemails.',
                'streefwaarde' => '5',
                'signaalwaarde' => '15',
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $kpi = KpiDefinitie::where('naam', 'Klikratio phishingsimulatie')->firstOrFail();

        $this->assertNull($kpi->meetbron);
        $this->assertTrue($kpi->isHandmatig());
        $this->assertSame('klikratio_phishingsimulatie', $kpi->sleutel);
        $this->assertSame(1, $kpi->definitie_versie);
        $this->assertSame(5.0, $kpi->streefwaarde);
    }

    /** De registry vult het formulier voor, zodat de gebruiker niets hoeft over te tikken. */
    public function test_een_gekozen_meetbron_vult_eenheid_richting_en_tekst_voor(): void
    {
        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('nieuweKpi')
            ->set('meetbron', 'reviewtaken_gem_overschrijding')
            ->assertSet('eenheid', 'dagen')
            ->assertSet('richting', 'omlaag')
            ->assertSet('berekeningswijze', fn (string $tekst) => str_contains($tekst, 'overschrijding in dagen'));
    }

    public function test_een_gewijzigde_meetbron_hoogt_de_definitieversie_op(): void
    {
        $kpi = $this->definitie('soa_geimplementeerd');
        $this->assertSame(1, $kpi->definitie_versie);

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $kpi->id)
            ->set('meetbron', 'soa_beoordeeld')
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertSame(2, $kpi->fresh()->definitie_versie);
    }

    public function test_naam_en_norm_wijzigen_laat_de_definitieversie_staan(): void
    {
        $kpi = $this->definitie('soa_beoordeeld');

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $kpi->id)
            ->set([
                'naam' => 'SoA-regels met een beslissing',
                'streefwaarde' => '98',
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $kpi->refresh();
        $this->assertSame(1, $kpi->definitie_versie);
        $this->assertSame(98.0, $kpi->streefwaarde);
    }

    /**
     * Een reeks waarin de eenheid halverwege omslaat is onherstelbaar gemengd:
     * teller en noemer betekenen dan iets anders dan ervoor.
     */
    public function test_de_eenheid_ligt_vast_zodra_er_een_meetpunt_is(): void
    {
        $this->legMeetpuntVast('soa_beoordeeld', 10, 10);
        $kpi = $this->definitie('soa_beoordeeld');

        $this->assertTrue($kpi->eenheidLigtVast());

        // Het scherm stuurt de eenheid niet meer mee, dus opslaan slaagt en de
        // eenheid blijft staan.
        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $kpi->id)
            ->set('eenheid', 'dagen')
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertSame('ratio', $kpi->fresh()->eenheid);

        // En buiten het scherm om weigert het model het alsnog: een regel die
        // alleen in één component staat, geldt niet voor de volgende aanroeper.
        $this->expectException(RuntimeException::class);
        $kpi->fresh()->update(['eenheid' => 'dagen']);
    }

    public function test_de_sleutel_is_nooit_te_wijzigen(): void
    {
        $this->expectException(RuntimeException::class);

        $this->definitie('soa_beoordeeld')->update(['sleutel' => 'iets_anders']);
    }

    public function test_een_kpi_met_metingen_gaat_op_inactief_en_niet_weg(): void
    {
        $this->legMeetpuntVast('soa_beoordeeld', 10, 10);
        $kpi = $this->definitie('soa_beoordeeld');

        $this->assertFalse($kpi->magVerwijderdWorden());

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('verwijder', $kpi->id)
            ->assertForbidden();

        $this->assertDatabaseHas('kpi_definities', ['id' => $kpi->id]);

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('zetActief', $kpi->id, false);

        $this->assertFalse($kpi->fresh()->actief);
        $this->assertSame(1, $kpi->fresh()->metingen()->count()); // historie intact
    }

    public function test_een_kpi_zonder_metingen_mag_wel_weg(): void
    {
        $kpi = $this->definitie('soa_geimplementeerd');

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('verwijder', $kpi->id);

        $this->assertDatabaseMissing('kpi_definities', ['id' => $kpi->id]);
    }

    /**
     * Een signaalwaarde aan de verkeerde kant van de streefwaarde laat de oranje
     * band verdwijnen: elk punt is dan groen of rood, en de semafoor zegt niets
     * meer.
     */
    public function test_de_signaalwaarde_moet_aan_de_juiste_kant_van_de_streefwaarde_liggen(): void
    {
        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $this->definitie('soa_beoordeeld')->id)
            ->set([
                'richting' => 'omhoog',
                'streefwaarde' => '90',
                'signaalwaarde' => '95',
            ])
            ->call('opslaan')
            ->assertHasErrors('signaalwaarde');
    }

    public function test_een_wijziging_komt_in_de_audit_trail(): void
    {
        $kpi = $this->definitie('soa_beoordeeld');

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $kpi->id)
            ->set('streefwaarde', '98')
            ->call('opslaan');

        $regel = AuditLogregel::where('entiteit_type', 'kpi_definitie')
            ->where('entiteit_id', $kpi->id)
            ->where('actie', 'gewijzigd')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('management-review-verbetercyclus', $regel->blok_naam);
        $this->assertSame('SoA-regels beoordeeld', $regel->entiteit_omschrijving);
        $this->assertArrayHasKey('streefwaarde', $regel->nieuwe_waarde);
    }

    // --- De norm als voorstel (implementatie/12e §9) ------------------------

    public function test_een_geseede_norm_staat_als_voorstel_en_telt_nog_niet_mee(): void
    {
        $kpi = $this->definitie('soa_beoordeeld');

        $this->assertSame(100.0, $kpi->streefwaarde);
        $this->assertFalse($kpi->streefwaardeIsVastgesteld());
        $this->assertNull($kpi->vastgesteldeStreefwaarde());

        $this->actingAs($this->ciso())->get('/meetaanpak')
            ->assertOk()
            ->assertSee('Voorstel')
            ->assertSee('Streefwaarde vaststellen');
    }

    /**
     * Vaststellen loopt via een bevestigingsscherm: het gevolg — een oordeel dat
     * een auditor als beleid leest — is niet uit de knop af te lezen.
     */
    public function test_de_ciso_stelt_het_voorstel_vast_na_bevestiging(): void
    {
        $kpi = $this->definitie('soa_beoordeeld');

        $component = Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->assertSet('toontStreefwaardeBevestiging', false)
            ->call('bevestigStreefwaarde', $kpi->id)
            ->assertSet('toontStreefwaardeBevestiging', true)
            ->assertSet('streefwaardeKpiId', $kpi->id)
            // Het scherm noemt de waarden waar het over gaat, en wat er met de
            // bestaande meetpunten gebeurt.
            ->assertSee('Streefwaarde vaststellen?')
            ->assertSee('SoA-regels beoordeeld')
            ->assertSee('onbepaald');

        // Openen alléén stelt niets vast.
        $this->assertFalse($kpi->fresh()->streefwaardeIsVastgesteld());

        $component->call('stelStreefwaardeVast', $kpi->id)
            ->assertSet('toontStreefwaardeBevestiging', false)
            ->assertSet('streefwaardeKpiId', null);

        $kpi->refresh();
        $this->assertTrue($kpi->streefwaardeIsVastgesteld());
        $this->assertSame(now()->toDateString(), $kpi->streefwaarde_vastgesteld_op->toDateString());
        $this->assertSame(100.0, $kpi->vastgesteldeStreefwaarde());
    }

    public function test_een_zelf_ingevoerde_norm_is_meteen_vastgesteld(): void
    {
        // Wie de waarde zelf intikt, heeft hem gekozen; de voorstel-status
        // bestaat alleen voor meegeleverde waarden.
        $kpi = $this->definitie('soa_geimplementeerd'); // heeft bewust geen norm
        $this->assertNull($kpi->streefwaarde);

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $kpi->id)
            ->set([
                'streefwaarde' => '80',
                'signaalwaarde' => '60',
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertTrue($kpi->fresh()->streefwaardeIsVastgesteld());
    }

    public function test_een_norm_leegmaken_trekt_de_vaststelling_in(): void
    {
        $kpi = $this->definitie('soa_beoordeeld');
        $kpi->update(['streefwaarde_vastgesteld_op' => now()]);

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $kpi->id)
            ->set([
                'streefwaarde' => '',
                'signaalwaarde' => '',
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $kpi->refresh();
        $this->assertNull($kpi->streefwaarde_vastgesteld_op);
        $this->assertFalse($kpi->streefwaardeIsVastgesteld());
    }

    /**
     * Langslopen om de naam te corrigeren is geen vaststelling. Zou het dat wel
     * zijn, dan wordt een meegeleverde norm alsnog stilzwijgend beleid — precies
     * wat deze kolom moet voorkomen.
     */
    public function test_opslaan_zonder_de_norm_aan_te_raken_stelt_hem_niet_vast(): void
    {
        $kpi = $this->definitie('soa_beoordeeld');

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $kpi->id)
            ->set('naam', 'SoA-regels met een beslissing')
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertFalse($kpi->fresh()->streefwaardeIsVastgesteld());
    }

    public function test_annuleren_laat_het_voorstel_een_voorstel(): void
    {
        $kpi = $this->definitie('soa_beoordeeld');

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bevestigStreefwaarde', $kpi->id)
            ->call('sluitStreefwaardeBevestiging')
            ->assertSet('toontStreefwaardeBevestiging', false)
            ->assertSet('streefwaardeKpiId', null);

        $this->assertFalse($kpi->fresh()->streefwaardeIsVastgesteld());
    }

    public function test_een_kpi_zonder_streefwaarde_is_niet_vast_te_stellen(): void
    {
        $zonderNorm = $this->definitie('soa_geimplementeerd');

        // Het bevestigingsscherm gaat niet eens open — er valt niets vast te
        // stellen — en de handeling zelf blijft ook los daarvan geblokkeerd.
        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bevestigStreefwaarde', $zonderNorm->id)
            ->assertForbidden();

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('stelStreefwaardeVast', $zonderNorm->id)
            ->assertForbidden();
    }

    // --- De methodevraag bij een handmatige KPI (implementatie/12f) ---------

    /** @return Testable */
    private function bewerkBerekeningswijze(KpiDefinitie $kpi, string $tekst)
    {
        return Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $kpi->id)
            ->set('berekeningswijze', $tekst)
            ->call('opslaan');
    }

    public function test_een_gewijzigde_methode_hoogt_de_definitieversie_op(): void
    {
        $kpi = $this->handmatigeKpi();   // staat op v3

        $component = $this->bewerkBerekeningswijze($kpi, 'Nieuwe simulatietool; andere populatie.')
            ->assertSet('toontMethodevraag', true)
            ->assertSee('Is de meetmethode veranderd?');

        // Vragen is nog niet opslaan.
        $this->assertSame(3, $kpi->fresh()->definitie_versie);

        $component->call('beantwoordMethodevraag', true)
            ->assertSet('toontMethodevraag', false)
            ->assertHasNoErrors();

        $kpi->refresh();
        $this->assertSame(4, $kpi->definitie_versie);
        $this->assertSame('Nieuwe simulatietool; andere populatie.', $kpi->berekeningswijze);
    }

    public function test_alleen_de_formulering_laat_de_definitieversie_staan(): void
    {
        $kpi = $this->handmatigeKpi();

        $this->bewerkBerekeningswijze($kpi, 'Klikkers gedeeld door verzonden simulatiemails (typefout hersteld).')
            ->call('beantwoordMethodevraag', false)
            ->assertHasNoErrors();

        $kpi->refresh();
        $this->assertSame(3, $kpi->definitie_versie);
        $this->assertStringContainsString('typefout hersteld', $kpi->berekeningswijze);
    }

    /**
     * De test die §3 zijn waarde geeft: zonder deze vastlegging is een
     * weggeklikte breuk achteraf onzichtbaar, en juist dát is de vraag die een
     * auditor bij een verdachte trend stelt.
     */
    public function test_de_ontkenning_wordt_vastgelegd_in_de_audit_trail(): void
    {
        $kpi = $this->handmatigeKpi();

        $this->bewerkBerekeningswijze($kpi, 'Zelfde methode, andere woorden.')
            ->call('beantwoordMethodevraag', false);

        $verklaring = AuditLogregel::where('entiteit_type', 'kpi_definitie')
            ->where('entiteit_id', $kpi->id)
            ->get()
            ->first(fn (AuditLogregel $r) => array_key_exists('methode_ongewijzigd_verklaard', $r->nieuwe_waarde ?? []));

        $this->assertNotNull($verklaring, 'De ontkenning hoort in de audit trail te staan.');
        $this->assertTrue($verklaring->nieuwe_waarde['methode_ongewijzigd_verklaard']);
        $this->assertSame('management-review-verbetercyclus', $verklaring->blok_naam);
    }

    /**
     * Bij een berekende KPI is de berekeningswijze proza óver code. De tekst
     * herformuleren verandert de berekening niet, dus een breuk melden zou
     * onwaar zijn (12f §2).
     */
    public function test_bij_een_berekende_kpi_wordt_de_vraag_nooit_gesteld(): void
    {
        $kpi = $this->definitie('soa_beoordeeld');

        $this->bewerkBerekeningswijze($kpi, 'Zelfde telling, duidelijker opgeschreven.')
            ->assertSet('toontMethodevraag', false)
            ->assertHasNoErrors();

        $kpi->refresh();
        $this->assertSame(1, $kpi->definitie_versie);
        $this->assertSame('Zelfde telling, duidelijker opgeschreven.', $kpi->berekeningswijze);
    }

    public function test_andere_velden_wijzigen_stelt_geen_vraag(): void
    {
        $kpi = $this->handmatigeKpi();

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $kpi->id)
            ->set([
                'naam' => 'Klikratio simulatie',
                'streefwaarde' => '4',
            ])
            ->call('opslaan')
            ->assertSet('toontMethodevraag', false)
            ->assertHasNoErrors();

        $this->assertSame(3, $kpi->fresh()->definitie_versie);
    }

    /** Meetbron én tekst tegelijk: dat is één betekeniswijziging, dus één ophoging. */
    public function test_meetbron_en_tekst_tegelijk_levert_precies_een_ophoging(): void
    {
        $kpi = $this->handmatigeKpi();

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $kpi->id)
            // Zet de meetbron: `updatedMeetbron` vult eenheid, richting én
            // berekeningswijze voor, dus meerdere velden wijzigen in één
            // handeling. Bewust een omlaag-bron, zodat streef 5 / signaal 15
            // geldig blijft en de test op de versietelling stuit en niet op
            // validatie.
            ->set('meetbron', 'reviewtaken_gem_overschrijding')
            ->call('opslaan')
            ->assertSet('toontMethodevraag', false)
            ->assertHasNoErrors();

        $this->assertSame(4, $kpi->fresh()->definitie_versie);
    }

    /** "Nee" onderdrukt alleen de tekst-bump, nooit die op de richting. */
    public function test_nee_antwoorden_onderdrukt_de_richtingbump_niet(): void
    {
        $kpi = $this->handmatigeKpi();   // richting omlaag

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('bewerk', $kpi->id)
            ->set([
                'berekeningswijze' => 'Zelfde methode, andere woorden.',
                'richting' => 'omhoog',
                'streefwaarde' => '95',
                'signaalwaarde' => '80',
            ])
            ->call('opslaan')
            ->call('beantwoordMethodevraag', false)
            ->assertHasNoErrors();

        $this->assertSame(4, $kpi->fresh()->definitie_versie);
    }

    public function test_een_nieuwe_handmatige_kpi_komt_op_versie_1_zonder_vraag(): void
    {
        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('nieuweKpi')
            ->set([
                'naam' => 'Patches binnen SLA',
                'meetbron' => '',
                'fase' => 'do',
                'berekeningswijze' => 'Patches binnen de SLA gedeeld door alle patches.',
            ])
            ->call('opslaan')
            ->assertSet('toontMethodevraag', false)
            ->assertHasNoErrors();

        $this->assertSame(1, KpiDefinitie::where('naam', 'Patches binnen SLA')->value('definitie_versie'));
    }

    // --- Handmatige meetpunten (implementatie/12e §5) -----------------------

    private function handmatigeKpi(): KpiDefinitie
    {
        return KpiDefinitie::create([
            'sleutel' => 'phishing_klikratio',
            'meetbron' => null,
            'naam' => 'Klikratio phishingsimulatie',
            'fase' => 'check',
            'eenheid' => 'ratio',
            'richting' => 'omlaag',
            'berekeningswijze' => 'Klikkers gedeeld door verzonden simulatiemails.',
            'streefwaarde' => 5,
            'signaalwaarde' => 15,
            // Zelf aangemaakt betekent zelf gekozen: het scherm zet deze datum
            // ook (12e §9).
            'streefwaarde_vastgesteld_op' => now(),
            'definitie_versie' => 3,
            'actief' => true,
        ]);
    }

    public function test_een_handmatig_meetpunt_legt_invoerder_versie_en_norm_vast(): void
    {
        $kpi = $this->handmatigeKpi();
        $ciso = $this->ciso();

        Livewire::actingAs($ciso)
            ->test(MeetaanpakOverzicht::class)
            ->call('nieuwMeetpunt', $kpi->id)
            ->set([
                'gemetenOp' => now()->toDateString(),
                'teller' => '7',
                'noemer' => '140',
                'meettoelichting' => 'Simulatieronde Q3.',
            ])
            ->call('slaMeetpuntOp')
            ->assertHasNoErrors();

        $meting = $kpi->metingen()->firstOrFail();

        $this->assertSame($ciso->id, $meting->ingevoerd_door_id);
        $this->assertSame(3, $meting->definitie_versie);   // van de definitie
        $this->assertSame(5.0, $meting->streefwaarde);
        $this->assertSame(15.0, $meting->signaalwaarde);
        $this->assertSame('Simulatieronde Q3.', $meting->toelichting);
        $this->assertSame($ciso->naam, $meting->herkomst());
    }

    public function test_een_tweede_meetpunt_in_dezelfde_maand_wordt_geweigerd(): void
    {
        $kpi = $this->handmatigeKpi();

        $component = Livewire::actingAs($this->ciso())->test(MeetaanpakOverzicht::class);

        foreach ([['7', '140'], ['9', '140']] as [$teller, $noemer]) {
            $component->call('nieuwMeetpunt', $kpi->id)
                ->set([
                    'gemetenOp' => now()->toDateString(),
                    'teller' => $teller,
                    'noemer' => $noemer,
                ])
                ->call('slaMeetpuntOp');
        }

        $component->assertHasErrors('gemetenOp');
        $this->assertSame(1, $kpi->metingen()->count());
        $this->assertSame(7, $kpi->metingen()->firstOrFail()->teller);
    }

    /**
     * Een berekende reeks met handmatige punten ertussen is niet meer
     * reproduceerbaar: het commando slaat die maand over en niemand kan later
     * nog zien welk punt waar vandaan kwam.
     */
    public function test_een_berekende_kpi_krijgt_geen_handmatig_meetpunt(): void
    {
        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('nieuwMeetpunt', $this->definitie('soa_beoordeeld')->id)
            ->assertForbidden();
    }

    public function test_een_meetpunt_in_de_toekomst_wordt_geweigerd(): void
    {
        $kpi = $this->handmatigeKpi();

        Livewire::actingAs($this->ciso())
            ->test(MeetaanpakOverzicht::class)
            ->call('nieuwMeetpunt', $kpi->id)
            ->set([
                'gemetenOp' => now()->addMonth()->toDateString(),
                'teller' => '7',
                'noemer' => '140',
            ])
            ->call('slaMeetpuntOp')
            ->assertHasErrors('gemetenOp');

        $this->assertSame(0, $kpi->metingen()->count());
    }

    public function test_een_lege_noemer_of_een_teller_boven_de_noemer_wordt_geweigerd(): void
    {
        $kpi = $this->handmatigeKpi();
        $component = Livewire::actingAs($this->ciso())->test(MeetaanpakOverzicht::class);

        $component->call('nieuwMeetpunt', $kpi->id)
            ->set([
                'gemetenOp' => now()->toDateString(),
                'teller' => '0',
                'noemer' => '0',
            ])
            ->call('slaMeetpuntOp')
            ->assertHasErrors('noemer');

        $component->set('teller', '200')
            ->set('noemer', '140')
            ->call('slaMeetpuntOp')
            ->assertHasErrors('teller');

        $this->assertSame(0, $kpi->metingen()->count());
    }

    /**
     * Test 7 uit 12e §10. Een meting is onveranderlijk, dus het component hoort
     * geen enkele muteer- of verwijderactie op een meetpunt te kennen. Deze
     * assertie faalt zodra iemand er één toevoegt — en dwingt daarmee een
     * bewuste keuze af in plaats van een stille.
     */
    public function test_er_is_geen_actie_om_een_meetpunt_te_wijzigen_of_te_verwijderen(): void
    {
        $publiek = collect((new \ReflectionClass(MeetaanpakOverzicht::class))->getMethods(
            \ReflectionMethod::IS_PUBLIC
        ))
            ->filter(fn (\ReflectionMethod $m) => $m->class === MeetaanpakOverzicht::class)
            ->map(fn (\ReflectionMethod $m) => $m->name)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'beantwoordMethodevraag',
            'bevestigStreefwaarde',
            'bewerk',
            'magMuteren',
            'nieuwMeetpunt',
            'nieuweKpi',
            'opslaan',
            'render',
            'slaMeetpuntOp',
            'sluitFormulier',
            'sluitMethodevraag',
            'sluitMeting',
            'sluitStreefwaardeBevestiging',
            'stelStreefwaardeVast',
            'updatedMeetbron',
            'verwijder',   // de KPI-definitie zonder metingen, niet een meetpunt
            'zetActief',
        ], $publiek);
    }

    public function test_auditor_ziet_het_scherm_maar_heeft_geen_muteeractie(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/meetaanpak')
            ->assertOk()
            ->assertDontSee('Nieuwe KPI');

        Livewire::actingAs($auditor)
            ->test(MeetaanpakOverzicht::class)
            ->assertSet('toontFormulier', false)
            ->call('nieuweKpi')
            ->assertForbidden();

        Livewire::actingAs($auditor)
            ->test(MeetaanpakOverzicht::class)
            ->call('verwijder', $this->definitie('soa_geimplementeerd')->id)
            ->assertForbidden();
    }
}
