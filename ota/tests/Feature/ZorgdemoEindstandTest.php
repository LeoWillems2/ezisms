<?php

namespace Tests\Feature;

use App\Models\Afwijking;
use App\Models\AuditLogregel;
use App\Models\Auditronde;
use App\Models\Beleidsversie;
use App\Models\Gebruiker;
use App\Models\Incident;
use App\Models\KpiDefinitie;
use App\Models\Maatregel;
use App\Models\Meting;
use App\Models\Risico;
use App\Models\SoaRegel;
use App\Models\Trainingsmodule;
use App\Models\Verbeteractie;
use App\Support\Audittrailketen;
use App\Support\Normprofiel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\VultDemoEenKeer;
use Tests\TestCase;

/**
 * De eindstand van de ZorgZeker-demo (`zorgdemo/scenario.md`), als assertions.
 *
 * Dezelfde opzet als `DemoEindstandTest`, op het nen7510-profiel. Wat hier
 * getoetst wordt, is wat deze demo anders maakt dan FruitBV: de 101 maatregelen
 * met de acht zorgspecifieke, de acceptatie door de manager, de gedane
 * datalekmelding, en dat er nergens demotekst op de plek van normtekst staat.
 * Het generieke mechanisme (autorisatie, klok, foutafhandeling) staat in
 * `DemoVulTest`; de volledige keten van de motor in `DemoEindstandTest`.
 */
#[Group('nen7510')]
class ZorgdemoEindstandTest extends TestCase
{
    use RefreshDatabase;
    use VultDemoEenKeer;

    /** De acht maatregelen die NEN 7510 bovenop Bijlage A van ISO 27001 legt. */
    private const ZORGSPECIFIEK = ['5.38', '5.39', '5.40', '5.41', '5.42', '5.43', '6.9', '8.35'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->vulDemoEenKeer();
    }

    protected function zetDemoProfiel(): void
    {
        config()->set('norm.actief', 'nen7510');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function fixture(string $naam): array
    {
        return json_decode(file_get_contents(base_path("../zorgdemo/data/{$naam}.json")), true);
    }

    public function test_de_demo_draait_op_het_zorgprofiel_met_vijf_accounts(): void
    {
        $this->assertTrue(Normprofiel::is('nen7510'));

        $accounts = Gebruiker::with('rollen')->get()
            ->mapWithKeys(fn (Gebruiker $g) => [$g->naam => $g->rollen->pluck('naam')->implode(',')]);

        $this->assertSame([
            'Saskia Salie' => 'CISO',
            'Maarten Munt' => 'Management',
            'Fleur Venkel' => 'Medewerker',
            'Bas Basilicum' => 'Administrator',
            'Karin Kamille' => 'Auditor',
        ], $accounts->all());

        $this->assertSame(5, Gebruiker::where('status', 'actief')->count());
        $this->assertSame(5, Gebruiker::where('email', 'like', '%@zorgzeker.example')->count());
    }

    /** "Alle 101 SoA-regels beoordeeld: 96 van toepassing, 5 niet; 8.35 staat in uitvoering." */
    public function test_de_soa_beoordeelt_alle_101_maatregelen(): void
    {
        $this->assertSame(101, SoaRegel::count());
        $this->assertSame(96, SoaRegel::where('van_toepassing', true)->count());
        $this->assertSame(0, SoaRegel::whereNull('van_toepassing')->count());

        $nietVanToepassing = SoaRegel::where('van_toepassing', false)->with('maatregel')->get()
            ->map(fn (SoaRegel $r) => $r->maatregel->annex_a_referentie)->sort()->values()->all();
        $this->assertSame(['8.25', '8.28', '8.30', '8.31', '8.4'], $nietVanToepassing,
            'De uitsluitingen volgen uit "ZorgZeker ontwikkelt geen software", niet uit de fysieke maatregelen van FruitBV.');

        $this->assertSame(88, SoaRegel::where('implementatiestatus', 'geimplementeerd')->count());
        $this->assertSame(8, SoaRegel::where('implementatiestatus', 'in_uitvoering')->count());

        $zeroTrust = SoaRegel::whereHas('maatregel', fn ($q) => $q->where('annex_a_referentie', '8.35'))->firstOrFail();
        $this->assertSame('in_uitvoering', $zeroTrust->implementatiestatus);
    }

    /** "De acht zorgspecifieke maatregelen hebben elk een eigen motivatie." */
    public function test_de_zorgspecifieke_maatregelen_hebben_hun_eigen_motivatie(): void
    {
        $motivaties = $this->fixture('soa')['motivaties']['per_referentie'];

        $this->assertEqualsCanonicalizing(self::ZORGSPECIFIEK, array_keys($motivaties));

        foreach (self::ZORGSPECIFIEK as $referentie) {
            $regel = SoaRegel::whereHas('maatregel', fn ($q) => $q->where('annex_a_referentie', $referentie))->firstOrFail();

            $this->assertTrue($regel->van_toepassing, "{$referentie} hoort van toepassing te zijn.");
            $this->assertSame($motivaties[$referentie], $regel->motivatie, "{$referentie} draagt niet de eigen motivatie.");
        }
    }

    /**
     * De afspraak uit het scenario (§0): de demo zet nergens tekst op de plek
     * waar normtekst hoort. De motivaties beschrijven wat ZorgZeker doet en
     * staan in de SoA; `omschrijving` en `zorgaanvulling` houden wat de seed
     * meelevert.
     */
    public function test_de_maatregelvelden_houden_de_tekst_uit_de_seed(): void
    {
        $seed = json_decode(file_get_contents(database_path('seeders/data/maatregelen-nen7510.json')), true)['maatregelen'];
        $seedOmschrijvingen = array_unique(array_column($seed, 'omschrijving'));

        $this->assertSame(
            [],
            Maatregel::whereNotIn('omschrijving', $seedOmschrijvingen)->pluck('annex_a_referentie')->all(),
            'Deze maatregelen hebben een omschrijving die niet uit de seed komt.',
        );

        foreach ($this->fixture('soa')['motivaties']['per_referentie'] as $referentie => $tekst) {
            $this->assertFalse(
                Maatregel::where('omschrijving', $tekst)->orWhere('zorgaanvulling', $tekst)->exists(),
                "De demomotivatie van {$referentie} staat in een maatregelveld in plaats van in de SoA.",
            );
        }
    }

    /**
     * "Risico 12 is door Maarten geaccepteerd boven de drempel." De directiehandeling
     * volgt hier uit het verhaal: er is geen alternatief voor het ECD.
     */
    public function test_de_afhankelijkheid_van_het_ecd_is_door_de_manager_geaccepteerd(): void
    {
        $risico = Risico::where('titel', 'Afhankelijkheid van één ECD-leverancier')->firstOrFail();

        $this->assertSame(16, $risico->risicoscore);
        $this->assertSame('geaccepteerd', $risico->status);
        $this->assertSame('Maarten Munt', $risico->behandelingen()->firstOrFail()->geaccepteerd_door);
        $this->assertContains('Management',
            Gebruiker::where('naam', 'Maarten Munt')->firstOrFail()->rollen->pluck('naam')->all());
    }

    /**
     * "Risico 15 heeft een lopend behandelplan." Niet elk nieuw risico is binnen
     * vijf maanden opgelost — en het scenario zegt dat het er zo bij staat.
     */
    public function test_het_nieuwe_ai_risico_heeft_een_lopend_behandelplan(): void
    {
        $risico = Risico::where('titel', 'like', 'Hulpverleners gebruiken een AI-spraak-naar-tekstdienst%')->firstOrFail();

        $this->assertSame(12, $risico->risicoscore);
        $this->assertSame('behandelplan_opgesteld', $risico->status);
    }

    /** De twee zorgrisico's die eerst boven de drempel staan, dalen met bewijs. */
    public function test_inzage_en_laptopverlies_zijn_onder_de_drempel_gebracht(): void
    {
        foreach ([
            'Inzage in een dossier door een collega zonder behandelrelatie' => 8,
            'Verlies of diefstal van een laptop of telefoon van een hulpverlener' => 6,
        ] as $titel => $score) {
            $risico = Risico::where('titel', $titel)->firstOrFail();

            $this->assertSame($score, $risico->risicoscore, $titel);
            $this->assertSame('gemitigeerd', $risico->status, $titel);
        }
    }

    /** "De minor uit M21 (exitplan ECD, 5.30) staat open; de corrigerende maatregel loopt." */
    public function test_de_minor_op_het_exitplan_loopt_nog(): void
    {
        $open = Afwijking::whereNull('gesloten_op')->get();

        $this->assertCount(1, $open);
        $this->assertStringContainsString('exitplan', $open->first()->omschrijving);
        $this->assertSame('non_conformiteit_gestart', $open->first()->bevinding()->firstOrFail()->status);

        $maatregel = $open->first()->maatregelen()->firstOrFail();
        $this->assertSame('in_uitvoering', $maatregel->status);
        $this->assertTrue($maatregel->deadline->isFuture());

        $this->assertSame(5, Afwijking::whereNotNull('gesloten_op')->count());
    }

    /**
     * "De datalekmelding van incident 1 is binnen de termijn gedaan." Het verschil
     * met FruitBV, waar de melding openstaat: hier bestaat `gemeld_na_uren`.
     */
    public function test_de_datalekmelding_is_binnen_de_termijn_gedaan(): void
    {
        $incident = Incident::where('titel', 'like', 'Voortgangsrapportage naar de verkeerde gemeente%')->firstOrFail();
        $melding = $incident->meldingen()->where('grondslag', 'avg')->firstOrFail();

        $this->assertTrue($melding->isGemeld());
        $this->assertFalse($melding->isTeLaat());
        $this->assertTrue($melding->gemeld_op->lessThanOrEqualTo($melding->uiterlijk_op));
        $this->assertSame('gesloten', $incident->status);
    }

    /** "De dekkingsmatrix toont de eerste schijf groen op één gat na: clausule 7.4." */
    public function test_de_dekkende_ronde_heeft_een_gat_met_reden(): void
    {
        $ronde = Auditronde::dekkend()->where('status', 'afgerond')->sole();

        $gaten = $ronde->auditobjecten()->wherePivot('afhandeling', 'niet_toegekomen')->get();

        $this->assertSame(['7.4'], $gaten->map->refCode()->all());
        $this->assertStringContainsString('verlof', $gaten->first()->pivot->toelichting);
        $this->assertSame($ronde->auditor_gebruiker_id, Gebruiker::where('naam', 'Karin Kamille')->value('id'));
    }

    /**
     * "Eén leesbevestiging (Maarten) staat open", en verder niets.
     *
     * Twee dingen maakten dat mogelijk (23-09-2026): de Auditor (Karin) kreeg
     * `uitvoeren` op beleid en bevestigt zelf, en de Administrator (Bas) valt
     * buiten de doelgroep, omdat hij het ISMS niet in kan. Daarvóór stonden
     * beiden bij elk document voor hun afdeling open.
     */
    public function test_de_openstaande_leesbevestigingen_zijn_die_uit_het_scenario(): void
    {
        $open = [];

        foreach (Beleidsversie::where('status', 'actief')->with('document')->get() as $versie) {
            if (! $versie->document->leesbevestiging_vereist) {
                continue;
            }

            foreach ($versie->document->doelgroepGebruikerIds() as $gebruikerId) {
                if (! $versie->isBevestigdDoor($gebruikerId)) {
                    $open[] = [$versie->document->titel, $versie->versienummer, Gebruiker::find($gebruikerId)->naam];
                }
            }
        }

        $this->assertSame([['Procedure cliëntidentificatie bij intake', 2, 'Maarten Munt']], $open);
    }

    /** "Eén trainingsherhaling (Fleur) staat open." */
    public function test_fleur_heeft_de_jaarlijkse_herhaling_niet_afgerond(): void
    {
        $fleur = Gebruiker::where('naam', 'Fleur Venkel')->firstOrFail();
        $basis = Trainingsmodule::where('titel', 'Informatiebeveiliging en privacy in de jeugdzorg')->firstOrFail();

        $voltooiingen = $basis->voltooiingen()->where('gebruiker_id', $fleur->id)->get();

        $this->assertCount(1, $voltooiingen);
        $this->assertTrue($voltooiingen->first()->verloopt_op->isPast());

        // De managementtraining van 6.9 is gevolgd.
        $leidinggevenden = Trainingsmodule::where('titel', 'Informatiebeveiliging voor leidinggevenden')->firstOrFail();
        $this->assertSame(1, $leidinggevenden->voltooiingen()->count());
    }

    /** "Eén verbeteractie uit directiebeoordeling 2 loopt nog." */
    public function test_de_verbetercyclus_heeft_nog_een_lopende_actie(): void
    {
        $this->assertSame(1, Verbeteractie::where('status', '!=', 'voltooid')->count());
    }

    /** De KPI die ZorgZeker buiten het ISMS meet: de steekproef op dossierinzage. */
    public function test_de_steekproef_op_dossierinzage_is_een_eigen_kpi(): void
    {
        $kpi = KpiDefinitie::where('sleutel', 'steekproef_inzage_zonder_relatie')->firstOrFail();

        $this->assertSame(4, Meting::where('kpi_definitie_id', $kpi->id)->count());
    }

    public function test_geen_enkele_gesimuleerde_gebeurtenis_ligt_in_de_toekomst(): void
    {
        $this->assertTrue(Carbon::parse(AuditLogregel::max('tijdstip'))->lessThanOrEqualTo(Carbon::now()));
    }

    public function test_de_keten_over_de_volledige_demo_trail_is_intact(): void
    {
        $uitkomst = Audittrailketen::controleer();

        $this->assertTrue($uitkomst->intact, 'Keten gebroken bij logregel '.$uitkomst->kapotte_id.'.');
        $this->assertGreaterThan(1000, $uitkomst->regels);
    }
}
