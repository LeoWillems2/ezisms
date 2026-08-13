<?php

namespace Tests\Feature;

use App\Models\Afwijking;
use App\Models\AuditLogregel;
use App\Models\Auditprogramma;
use App\Models\Auditronde;
use App\Models\Beleidsversie;
use App\Models\Bevinding;
use App\Models\CorrigerendeMaatregel;
use App\Models\Gebruiker;
use App\Models\Issue;
use App\Models\KpiDefinitie;
use App\Models\Leesbevestiging;
use App\Models\Meting;
use App\Models\RestrisicoSnapshot;
use App\Models\Reviewsessie;
use App\Models\Risico;
use App\Models\RisicocriteriaVersie;
use App\Models\SoaRegel;
use App\Models\Taak;
use App\Models\Trainingsmodule;
use App\Models\Verbeteractie;
use App\Models\Wijziging;
use App\Support\Audittrailketen;
use App\Support\Demo\Klok;
use App\Support\ToetsBestanden;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * De eindstandcontroles uit M22 van `tijdlijn.json`, als assertions.
 *
 * Dit is de echte poort onder de simulatiemotor: hij faalt zodra het scenario en
 * de motor uit elkaar lopen (`saasdemo/simulatiemotor.md` §11). Wijkt de gevulde
 * demo af van §9 M22 in `scenario.md`, dan is het scenario leidend en de motor
 * fout — tenzij het scenario zelf niet kan kloppen, en dan wordt het scenario
 * aangepast met een aantekening.
 *
 * De volledige tijdlijn wordt één keer gevuld en daarna toetst elke methode een
 * deel van de eindstand; opnieuw vullen per methode is de tijd niet waard. Het
 * mechanisme zelf (autorisatie, klok, foutafhandeling) staat in `DemoVulTest`,
 * op een verkorte tijdlijn.
 */
class DemoEindstandTest extends TestCase
{
    use RefreshDatabase;

    /** Eén vulling per proces; zie de toelichting bij setUp(). */
    private static bool $gevuld = false;

    /**
     * Vult de tijdlijn één keer en laat elke test daarop draaien
     * (implementatie/00f §2).
     *
     * `RefreshDatabase` migreert één keer per proces en zet elke test in een
     * transactie die terugrolt. Vullen in `setUp()` betekende dus vijftien keer
     * 23 maanden simulatie: 85 seconden voor dit ene bestand. De haken die
     * daarvoor bedoeld lijken helpen niet — `afterRefreshingDatabase()` draait
     * alsnog per test, en `migrateDatabases()` vuurt alleen voor de eerste
     * testklasse in het proces.
     *
     * Daarom: uit de transactie stappen, vullen, en er weer in. De vulling
     * overleeft daarmee de rollback na elke test; de test zelf draait in zijn
     * eigen transactie erbovenop.
     *
     * **De prijs staat in `Tests\TestCase`:** de gevulde tabellen blijven ook ná
     * deze klasse staan. Die bewaking faalt luidruchtig als een andere klasse
     * erna begint, in plaats van stilletjes op FruitBV-gegevens te toetsen.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Geen databasestaat: dit hoort per test schoon te zijn.
        Storage::fake('bewijs');
        // Ook de toetsen-disk: de demo zet er een toetsbestand op, en dat hoort
        // niet in de echte storage/ van wie de suite draait terecht te komen.
        Storage::fake(ToetsBestanden::DISK);

        if (self::$gevuld) {
            return;
        }

        $this->app['env'] = 'local';

        // De simulatiemotor is ISO-only en het commando weigert in zorgmodus
        // (nen7510-opzet.md §4.8). Expliciet zetten, zodat dit bestand ook
        // overeind blijft bij een run met ISMS_NORM=nen7510 — die run is de
        // controle of de applicatie profielvast is (00k §3).
        config()->set('norm.actief', 'iso27001');

        // De vulling gebruikt `truncate()` en geen `migrate:fresh`; op sqlite
        // compileert dat naar DELETE FROM en blijft het binnen één verbinding.
        // Op MySQL zou TRUNCATE een impliciete commit geven — de suite draait op
        // sqlite, maar dat is geen vanzelfsprekendheid.
        DB::rollBack();

        // Zonder de opgevangen uitvoer meldt een mislukte vulling alleen "exit
        // code 1", en dan begint het zoeken pas. De motor zegt zelf precies bij
        // welke maand en welke gebeurtenis hij is gestopt.
        $exit = $this->withoutMockingConsoleOutput()
            ->artisan('isms:demo-vul', ['--stil' => true]);

        self::$gevuld = true;
        DB::beginTransaction();

        $this->assertSame(0, $exit, "Het vullen is mislukt:\n".Artisan::output());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** "De minor uit M21 staat op 'non_conformiteit_gestart'; de corrigerende maatregel is 'in_uitvoering'." */
    public function test_de_openstaande_minor_uit_de_opvolgingsaudit_loopt_nog(): void
    {
        $open = Afwijking::whereNull('gesloten_op')->get();

        $this->assertCount(1, $open, 'Er hoort precies één afwijking open te staan in de eindstand.');
        $this->assertSame('actie_lopend', $open->first()->status);
        $this->assertStringContainsString('continuïteitsplan', $open->first()->omschrijving);

        $bevinding = $open->first()->bevinding()->first();
        $this->assertNotNull($bevinding, 'De open afwijking komt uit een auditbevinding.');
        $this->assertSame('non_conformiteit_gestart', $bevinding->status);

        $maatregel = $open->first()->maatregelen()->firstOrFail();
        $this->assertSame('in_uitvoering', $maatregel->status);
        $this->assertTrue(
            $maatregel->deadline->isFuture(),
            'De deadline van de lopende maatregel ligt zes weken ná nu en hoort dus in de toekomst.',
        );

        // Alle andere afwijkingen zijn netjes de hele cyclus door gegaan.
        $this->assertSame(5, Afwijking::whereNotNull('gesloten_op')->count());
        $this->assertSame(
            5,
            CorrigerendeMaatregel::where('status', 'voltooid')->whereHas('toetsen')->count(),
            'Elke gesloten afwijking hoort een voltooide én getoetste maatregel te hebben.',
        );
    }

    /** "Programmajaar 1 loopt nog; jaar 2 en 3 staan gepland met rondes op 'gepland'." */
    public function test_de_certificeringscyclus_staat_klaar_voor_de_resterende_jaren(): void
    {
        $cyclus = Auditprogramma::where('aard', 'certificeringscyclus')->firstOrFail();

        $this->assertSame('actief', $cyclus->status);
        $this->assertSame(3, $cyclus->aantal_jaren);
        $this->assertSame(3, $cyclus->auditplannen()->count());

        // Jaar 1 is uitgevoerd (M18), jaar 2 en 3 staan nog gepland.
        $rondes = Auditronde::whereIn('auditplan_id', $cyclus->auditplannen()->pluck('id'))->get();

        $this->assertSame(1, $rondes->where('status', 'afgerond')->count());
        $this->assertSame(2, $rondes->where('status', 'gepland')->count());
    }

    /** "Eén afgeronde dekkende ronde (M18); het voorbereidingsprogramma telt niet mee." */
    public function test_alleen_de_ronde_van_programmajaar_1_telt_mee_voor_de_dekking(): void
    {
        $this->assertSame(
            1,
            Auditronde::dekkend()->where('status', 'afgerond')->count(),
            'Precies één afgeronde ronde hoort de dekkingsmatrix te kleuren.',
        );

        $voorbereiding = Auditprogramma::where('aard', 'voorbereiding')->firstOrFail();
        $this->assertSame('afgesloten', $voorbereiding->status);

        $rondes = Auditronde::whereIn('auditplan_id', $voorbereiding->auditplannen()->pluck('id'))->get();

        $this->assertCount(2, $rondes, 'De opstartfase kende een nulmeting en een interne audit.');
        $this->assertTrue(
            $rondes->every(fn (Auditronde $r) => $r->telt_mee_voor_dekking === false),
            'Geen van beide rondes uit de opstartfase mag meetellen voor de dekking.',
        );
        $this->assertTrue($rondes->every(fn (Auditronde $r) => $r->isAfgerond()));
    }

    /**
     * "Restrisico-snapshots zijn vastgelegd voor de gepasseerde jaargrenzen."
     *
     * Een tijdlijn van 22 maanden passeert twee keer 31 december, maar niet elke
     * jaargrens levert een snapshot op: `isms:leg-restrisico-vast` schrijft
     * alleen rijen voor controls met een gekoppeld risico, en die koppeling
     * ontstaat pas met de behandelplannen van M4. Valt de eerste jaargrens
     * daarvóór — wat gebeurt bij een vulling in juli — dan hoort daar terecht
     * niets te staan.
     *
     * Dat is geen fout maar een gevolg van de relatieve tijdlijn: welke maanden
     * een 31 december bevatten, hangt van de draaidag af. De verwachting wordt
     * daarom uit dezelfde klok afgeleid als de motor gebruikt, in plaats van op
     * een vast aantal gezet.
     */
    /**
     * De gesimuleerde geschiedenis houdt op bij vandaag.
     *
     * Het anker stond op de lopende maand, waardoor alles wat het scenario in
     * M22 op de maandgrens deed op de laatste dag van díe maand landde — tot
     * dertig dagen ná de draaidag. Op `/audit-log` (aflopend op tijdstip) stonden
     * die regels dan boven het echte werk van vandaag, met een tijd van 00:00:00
     * omdat de demoklok per dag stapt. Dat leest als een kapotte sortering
     * terwijl de sortering klopt: de gegevens klopten niet.
     *
     * Op de trail en niet op één tabel, want dit is precies het scherm waar het
     * opviel — en elke gesimuleerde handeling komt er langs.
     */
    public function test_geen_enkele_gesimuleerde_gebeurtenis_ligt_in_de_toekomst(): void
    {
        $laatste = AuditLogregel::max('tijdstip');

        $this->assertNotNull($laatste, 'De demo hoort een audit trail achter te laten.');
        $this->assertTrue(
            Carbon::parse($laatste)->lessThanOrEqualTo(Carbon::now()),
            "De laatste trailregel staat op {$laatste}, ná nu. Het anker van de demoklok laat de tijdlijn voorbij vandaag lopen.",
        );

        // Bewust géén ondergrens op het einde van M22: nadat de motor de klok
        // heeft hersteld schrijft de vulling nog regels op de échte tijd. Die
        // horen er en liggen tussen het einde van M22 en nu. "Niets ná nu" is
        // hier de hele belofte.
    }

    public function test_de_gepasseerde_jaargrenzen_hebben_een_restrisico_snapshot(): void
    {
        $klok = new Klok;
        $eersteBehandelplannen = 4;

        $verwacht = collect(range(0, Klok::AANTAL_MAANDEN))
            ->filter(fn (int $maand) => $maand >= $eersteBehandelplannen)
            ->flatMap(fn (int $maand) => $klok->jaargrenzenIn($maand))
            ->map(fn ($grens) => $grens->year)
            ->sort()
            ->values();

        $this->assertNotEmpty($verwacht, 'De tijdlijn passeert geen bruikbare jaargrens.');

        $this->assertSame(
            $verwacht->all(),
            RestrisicoSnapshot::distinct()->pluck('peiljaar')->map(fn ($j) => (int) $j)->sort()->values()->all(),
        );

        // En de snapshots zijn niet leeg: de koppeling risico → behandeling →
        // control is wat er te trenden valt.
        $this->assertGreaterThan(0, RestrisicoSnapshot::whereNotNull('max_restrisico')->count());
    }

    /** "Eén account is gedeactiveerd (Keesje), zeven zijn actief." */
    public function test_de_accountlevenscyclus_is_zichtbaar(): void
    {
        $this->assertSame(7, Gebruiker::where('status', 'actief')->count());

        $uitDienst = Gebruiker::where('status', 'gedeactiveerd')->get();

        $this->assertCount(1, $uitDienst);
        $this->assertSame('Keesje Kers', $uitDienst->first()->naam);
        $this->assertNotNull($uitDienst->first()->accounts_ingetrokken_op);
    }

    /**
     * "Twee leesbevestigingen staan open."
     *
     * De twee uit `beleid.json` staan er inderdaad. Daarnaast ontbreken de
     * bevestigingen van de interne auditor, en dat is géén fout in de motor: de
     * Auditor-rol heeft geen `uitvoeren` op `beleid-maatregelbeheer` en kan dus
     * geen leesbevestiging afleggen. Namens hem tekenen is geen optie — een
     * leesbevestiging kan alleen de lezer zelf afleggen. Zolang die rechtenkeuze
     * staat, hoort dit gat er te zijn; wordt hij herzien, dan faalt deze test en
     * hoort hij aangepast te worden.
     */
    public function test_de_openstaande_leesbevestigingen_zijn_die_uit_het_scenario(): void
    {
        $auditor = Gebruiker::where('naam', 'Aurelius Aardappel')->firstOrFail();
        $open = [];

        foreach (Beleidsversie::where('status', 'actief')->with('document')->get() as $versie) {
            if (! $versie->document->leesbevestiging_vereist) {
                continue;
            }

            foreach ($versie->document->doelgroepGebruikerIds() as $gebruikerId) {
                if (! $versie->isBevestigdDoor($gebruikerId)) {
                    $open[] = [$versie->document->titel, Gebruiker::find($gebruikerId)->naam];
                }
            }
        }

        $namen = collect($open)->pluck(1);

        $this->assertContains('Piet Peer', $namen->all());
        $this->assertContains('Kees Karot', $namen->all());

        // Alles wat verder openstaat is van de auditor, om de reden hierboven.
        $overig = collect($open)
            ->reject(fn (array $regel) => in_array($regel[1], ['Piet Peer', 'Kees Karot'], true))
            ->pluck(1)
            ->unique();

        $this->assertSame(
            [$auditor->naam],
            $overig->values()->all(),
            'Onverwachte openstaande leesbevestigingen: '.$overig->implode(', '),
        );

        $this->assertGreaterThan(0, Leesbevestiging::count(), 'Er hoort wél bevestigd te zijn.');
    }

    /** "Eén trainingsherhaling staat open." */
    public function test_een_medewerker_heeft_de_jaarlijkse_herhaling_niet_afgerond(): void
    {
        $piet = Gebruiker::where('naam', 'Piet Peer')->firstOrFail();
        $basis = Trainingsmodule::where('titel', 'Basis informatiebeveiliging en AVG')->firstOrFail();

        // Ronde 1 (M6) heeft hij gedaan, ronde 2 (M18) niet — dus precies één
        // voltooiing, en die is inmiddels verlopen.
        $voltooiingen = $basis->voltooiingen()->where('gebruiker_id', $piet->id)->get();

        $this->assertCount(1, $voltooiingen);
        $this->assertTrue(
            $voltooiingen->first()->verloopt_op->isPast(),
            'De voltooiing van M6 hoort na twaalf maanden verlopen te zijn.',
        );

        // De rest heeft de herhaling wél gedaan.
        $this->assertSame(2, $basis->voltooiingen()->where('gebruiker_id', '!=', $piet->id)
            ->get()->groupBy('gebruiker_id')->first()->count());
    }

    /** "Eén verbeteractie uit directiebeoordeling 2 loopt nog." */
    public function test_de_verbetercyclus_heeft_nog_een_lopende_actie(): void
    {
        $this->assertSame(2, Reviewsessie::where('status', 'gehouden')->count());

        $open = Verbeteractie::where('status', 'open')->get();

        $this->assertCount(1, $open, 'Een verbetercyclus waarin alles altijd af is, is ongeloofwaardig.');
        $this->assertStringContainsString('KPI-set', $open->first()->omschrijving);
        $this->assertSame(4, Verbeteractie::where('status', 'voltooid')->count());
    }

    /**
     * "Drie wijzigingen: één afgerond, één midden in de reeks, één spoedgeval
     * waarvan de goedkeuring achteraf nog openstaat." (blok 15, A.8.32)
     *
     * Die derde is het punt van de hele set: een register met alleen nette
     * dossiers stuurt niets. De demo hoort het gap-signaal te laten zien dat een
     * CISO in het echt ook zou zien.
     */
    public function test_het_wijzigingenregister_toont_een_afgerond_een_lopend_en_een_spoedgeval(): void
    {
        $this->assertSame(3, Wijziging::count());

        $afgerond = Wijziging::where('status', 'gesloten')->get();
        $this->assertCount(1, $afgerond);
        $this->assertTrue($afgerond->first()->geslaagd);
        $this->assertFalse($afgerond->first()->teruggedraaid);

        // Elke uitgevoerde wijziging heeft een terugvalplan — dat kán ook niet
        // anders, de uitvoerstap weigert zonder (A.8.32 f). De KPI die daarop
        // staat hoort in de demo dus op 100% uit te komen.
        $this->assertSame(0, Wijziging::query()->uitgevoerdZonderTerugvalplan()->count());

        $spoed = Wijziging::where('zwaarte', 'spoed')->firstOrFail();
        $this->assertSame('uitgevoerd', $spoed->status);
        $this->assertTrue(
            Taak::where('gekoppeld_entiteit_type', 'wijziging')
                ->where('gekoppeld_entiteit_id', $spoed->id)
                ->where('vraagt_uitkomst', true)
                ->whereNull('uitkomst')
                ->exists(),
            'De spoedwijziging hoort nog op haar goedkeuring achteraf te wachten.',
        );

        // De reeks staat op naam van twee mensen: de beheerder voert uit, de
        // CISO autoriseert. Een reeks volledig op één naam toont geen
        // functiescheiding.
        $namen = AuditLogregel::where('entiteit_type', 'taak')
            ->whereIn('entiteit_id', Taak::where('gekoppeld_entiteit_type', 'wijziging')->select('id'))
            ->pluck('gebruiker_naam')
            ->unique();

        $this->assertGreaterThan(1, $namen->count(), 'De stappen horen niet allemaal van één persoon te zijn.');
    }

    /** "Risico 15 is door de directie geaccepteerd; risico 16 heeft sinds M20 een behandelplan." */
    public function test_het_restrisico_boven_de_drempel_is_door_de_directie_geaccepteerd(): void
    {
        $risico = Risico::where('titel', 'Afhankelijkheid van één betaaldienstverlener')->firstOrFail();

        $this->assertSame(16, $risico->risicoscore);
        $this->assertTrue($risico->boventDrempel());
        $this->assertSame('geaccepteerd', $risico->status);

        $behandeling = $risico->behandelingen()->firstOrFail();
        $this->assertSame('accepteren', $behandeling->behandeloptie);
        $this->assertSame('Baas Prei', $behandeling->geaccepteerd_door);

        // De directie tekende: een Management-account, niet de CISO.
        $tekenaar = Gebruiker::where('naam', 'Baas Prei')->firstOrFail();
        $this->assertContains('Management', $tekenaar->rollen->pluck('naam')->all());

        $nieuw = Risico::where('titel', 'AI-plugin in de klantportal verwerkt ordergegevens')->firstOrFail();
        $this->assertTrue($nieuw->behandelingen()->exists(), 'Risico 16 hoort sinds M20 een behandelplan te hebben.');
    }

    /**
     * De risicocriteria als vastgesteld kader (04g). De demo laat de enige
     * schermflow zien waarin CISO en directie samenwerken: de CISO stelt v2 op,
     * de directie stelt hem vast, en het bestaande register beweegt mee.
     */
    public function test_de_directie_heeft_de_aangescherpte_risicocriteria_vastgesteld(): void
    {
        $actief = RisicocriteriaVersie::actief();

        $this->assertSame(2, $actief->versienummer);
        $this->assertSame(12, $actief->drempelwaarde_score);
        $this->assertSame('Bobo Spruitje', $actief->goedgekeurd_door);
        $this->assertSame('vervangen', RisicocriteriaVersie::where('versienummer', 1)->firstOrFail()->status);

        // Vaststellen is een directiehandeling: een Management-account, niet de CISO.
        $this->assertContains(
            'Management',
            Gebruiker::where('naam', 'Bobo Spruitje')->firstOrFail()->rollen->pluck('naam')->all(),
        );

        // Versie 2 voegt de kwantitatieve band toe; dat is wat een op cijfers
        // sturende auditor leest.
        $this->assertStringContainsString(
            'jaaromzet',
            $actief->niveausVan('impact')[4]->kwantitatieve_band,
        );

        // De risico's die vóór M20 beoordeeld zijn dragen nog v1: de historie is
        // geen functie van het kader van vandaag.
        $onderV1 = Risico::where('risicocriteria_versie_id', RisicocriteriaVersie::where('versienummer', 1)->value('id'))->count();
        $this->assertGreaterThan(0, $onderV1);

        // En de aanscherping heeft opvolging opgeleverd in plaats van alleen een
        // ander getal.
        $this->assertTrue(
            Taak::where('soort', 'risico-herbeoordeling-criteria')->exists(),
            'Een scherpere acceptatiedrempel hoort herbeoordelingstaken op te leveren.',
        );
    }

    /** De SoA-eindstand uit `soa.json`: 88 van toepassing, ~80 geïmplementeerd. */
    /**
     * De doorvertaling van §4.1 naar §6.1 (plan 02b), inclusief de twee gaten
     * die er bewust in zitten — zie `_over.aanleidingen` in `risicos.json`.
     * Een demo waarin alles gedekt is, laat het dekkingssignaal juist niet zien.
     */
    public function test_de_context_kwesties_zijn_doorvertaald_naar_risicos(): void
    {
        $this->assertSame(14, Risico::has('aanleidingen')->count());

        // Precies één kwestie zonder risico: de certificeringseis van klanten,
        // die in beleid en in het auditprogramma landt en niet in een risico.
        $onbedekt = Issue::doesntHave('risicos')->pluck('categorie');
        $this->assertSame(['Markt'], $onbedekt->all());
    }

    public function test_de_soa_staat_op_de_eindstand_uit_het_scenario(): void
    {
        $this->assertSame(93, SoaRegel::count());
        $this->assertSame(88, SoaRegel::where('van_toepassing', true)->count());
        $this->assertSame(5, SoaRegel::where('van_toepassing', false)->count());
        $this->assertSame(0, SoaRegel::whereNull('van_toepassing')->count());

        $this->assertSame(80, SoaRegel::where('implementatiestatus', 'geimplementeerd')->count());
        $this->assertSame(8, SoaRegel::where('implementatiestatus', 'in_uitvoering')->count());

        // Plan 04d fase 2: drie regels met een eigen vaststelling, waarvan twee
        // afwijkend van het meegeleverde uitgangspunt.
        $eigen = SoaRegel::whereNotNull('kenmerken_eigen')->with('maatregel')->get();

        $this->assertCount(3, $eigen);
        $this->assertSame(2, $eigen->filter(fn (SoaRegel $r) => $r->wijktAfVanUitgangspunt())->count());
    }

    /**
     * De demo bestaat om een geloofwaardige trail te tonen; koppelingen waren
     * daarin het grootste gat (06b §9).
     */
    public function test_koppelwijzigingen_staan_in_de_trail_op_naam_van_de_handelende_gebruiker(): void
    {
        $regels = AuditLogregel::where('actie', 'gewijzigd')
            ->where(fn ($q) => $q->where('nieuwe_waarde', 'like', '%gekoppeld%')
                ->orWhere('oude_waarde', 'like', '%ontkoppeld%'))
            ->get();

        $this->assertGreaterThan(20, $regels->count(),
            'De gevulde demo hoort koppelwijzigingen in de audit trail te hebben.');

        $this->assertSame([], $regels->where('gebruiker_id', null)->pluck('entiteit_omschrijving')->all(),
            'Koppelingen uit de tijdlijn horen op naam van een persoon te staan, niet op "Systeem".');

        // Namen en niet id's: daar staat of valt de leesbaarheid mee.
        $soaKoppeling = $regels->first(fn (AuditLogregel $r) => isset($r->nieuwe_waarde['maatregelen']));
        $this->assertNotNull($soaKoppeling, 'Geen enkele SoA-koppeling in de trail.');
        $this->assertMatchesRegularExpression('/^\d+ gekoppeld: A\./', $soaKoppeling->nieuwe_waarde['maatregelen']);
    }

    /**
     * De zwaarste ketentest die er is (implementatie/06c §9): de simulatiemotor
     * schrijft duizenden regels, door elkaar, namens verschillende gebruikers en
     * met een verzette klok. Klopt de keten daar, dan klopt hij.
     */
    public function test_de_keten_over_de_volledige_demo_trail_is_intact(): void
    {
        $uitkomst = Audittrailketen::controleer();

        $this->assertTrue($uitkomst->intact, 'Keten gebroken bij logregel '.$uitkomst->kapotte_id.'.');
        $this->assertGreaterThan(1000, $uitkomst->regels);
        $this->assertSame(Audittrailketen::kop(), $uitkomst->kophash);
        $this->assertSame(0, AuditLogregel::whereNull('hash')->count());
    }

    /** Geen enkele handeling uit de tijdlijn hoort op "Systeem" te staan. */
    public function test_de_bevindingen_zijn_door_de_interne_auditor_vastgelegd(): void
    {
        $auditor = Gebruiker::where('naam', 'Aurelius Aardappel')->firstOrFail();

        $interneRondes = Auditronde::whereIn('type', Auditronde::INTERNE_TYPEN)->get();

        // Drie uitgevoerd (nulmeting, voorbereiding, programmajaar 1) en twee
        // gepland: de cyclus plant ook alvast jaar 2 en 3.
        $this->assertCount(5, $interneRondes);

        $uitgevoerd = $interneRondes->where('status', 'afgerond');
        $this->assertCount(3, $uitgevoerd);
        $this->assertTrue(
            $uitgevoerd->every(fn (Auditronde $r) => $r->auditor_gebruiker_id === $auditor->id),
            'Een uitgevoerde interne ronde hoort aan de interne auditor toegewezen te zijn.',
        );

        // De auditor van een nog niet gestarte ronde blijft bewust open: die
        // wijst de CISO per ronde toe (implementatie/11 §4).
        $this->assertTrue(
            $interneRondes->where('status', 'gepland')
                ->every(fn (Auditronde $r) => $r->auditor_gebruiker_id === null),
        );

        // De externe rondes hebben geen platformaccount maar een naam.
        $externe = Auditronde::whereNotIn('type', Auditronde::INTERNE_TYPEN)->get();

        $this->assertCount(3, $externe, 'Certificering fase 1 en 2, plus één opvolgingsaudit.');
        $this->assertTrue($externe->every(fn (Auditronde $r) => $r->extern_auditor_naam !== null));

        $this->assertSame(6 + 22 + 9, Bevinding::count());
    }

    /**
     * De Act-fase meet nu ook gebeurtenissen (implementatie/12g). Het scenario
     * beschreef het patroon al — de scoredaling van risico 5 in M8 volgt op de
     * hersteltest, die van risico 8 in M17 op de patchronde — maar de tijdlijn
     * koppelde dat bewijs alleen aan de afwijking en niet aan het risico.
     * Zonder die koppeling staat `scoredaling_zonder_bewijs` 22 maanden lang
     * op 100% en demonstreert de demo een meter die stuk lijkt.
     */
    public function test_de_act_gebeurtenismetingen_bewegen_in_de_demo(): void
    {
        foreach (['nieuwe_risicos', 'behandelplannen_afgerond', 'scoredaling_zonder_bewijs'] as $sleutel) {
            $definitie = KpiDefinitie::where('sleutel', $sleutel)->firstOrFail();
            $metingen = $definitie->metingen()->orderBy('gemeten_op')->get();

            $this->assertGreaterThan(1, $metingen->count(), "{$sleutel} hoort een reeks te hebben.");
            $this->assertTrue(
                $metingen->every(fn (Meting $m) => $m->isGebeurtenis()),
                "{$sleutel} is een gebeurtenismeting en hoort een periode te dragen.",
            );

            // Opeenvolgende vensters sluiten exact op elkaar aan: geen gat waarin
            // gebeurtenissen verdwijnen, geen overlap waarin ze dubbel tellen.
            $metingen->skip(1)->each(function (Meting $m, int $i) use ($metingen) {
                // `skip()` behoudt de oorspronkelijke sleutels, dus $i is de
                // index van dit meetpunt zelf.
                $this->assertEquals($metingen[$i - 1]->periode_tot, $m->periode_van);
            });
        }

        $dalingen = KpiDefinitie::where('sleutel', 'scoredaling_zonder_bewijs')->firstOrFail()
            ->metingen()->get();

        $this->assertTrue(
            $dalingen->contains(fn (Meting $m) => $m->percentage() < 100.0),
            'Minstens één periode hoort een scoredaling mét onderbouwing te bevatten.',
        );
    }

    /**
     * De meetaanpak is organisatie-eigen geworden (implementatie/12e §9): FruitBV
     * heeft een deel van de meegeleverde normvoorstellen vastgesteld, één bewust
     * opengelaten, en meet daarnaast iets buiten het ISMS.
     */
    public function test_de_beheerderstraining_draagt_een_werkende_toets(): void
    {
        $module = Trainingsmodule::where('titel', 'Veilig beheer van de productieomgeving')->firstOrFail();

        $this->assertSame('owasp1.html', $module->toets_bestand);

        // Dát het bestand op de disk terechtkomt, toetst DemoVulTest: die vult
        // binnen één test, terwijl deze klasse één keer vult en de nepdisk per
        // test opnieuw leegmaakt. Hier gaat het om wat er meegeleverd wórdt.
        $bron = base_path('../saasdemo/data/toetsen/owasp1.html');
        $this->assertFileExists($bron);

        // En hij meldt terug. Zonder een aanroep van onQuizVoltooid maakt de
        // deelnemer de toets, ziet hij geen fout, en komt er niets binnen —
        // precies wat er op 11-08-2026 in de meegeleverde toetsen misging.
        // Beide namen mogen — `onQuizVoltooid` en de wrapper `triggerQuizVoltooid`
        // doen hetzelfde — maar er moet er één aangeroepen worden, en op allebei
        // de takken. Een toets die alleen bij slagen terugmeldt, verzwijgt de
        // gezakte pogingen.
        $html = (string) file_get_contents($bron);
        $this->assertMatchesRegularExpression('/(onQuizVoltooid|triggerQuizVoltooid)\(score, total, true\)/', $html);
        $this->assertMatchesRegularExpression('/(onQuizVoltooid|triggerQuizVoltooid)\(score, total, false\)/', $html);
    }

    public function test_fruitbv_heeft_eigen_normen_en_een_handmatige_kpi(): void
    {
        $vastgesteld = KpiDefinitie::whereNotNull('streefwaarde_vastgesteld_op')->pluck('sleutel');

        $this->assertContains('soa_beoordeeld', $vastgesteld);
        $this->assertContains('reviewtaken_op_tijd', $vastgesteld);

        // Bewust opengelaten: die staat op het dashboard als "geen norm
        // vastgesteld", en dat is een realistische stand — niet elke KPI heeft
        // al een bestuurlijk besluit achter zich.
        $this->assertNotContains('reviewtaken_gem_overschrijding', $vastgesteld);

        $handmatig = KpiDefinitie::where('sleutel', 'phishing_klikratio')->firstOrFail();

        $this->assertTrue($handmatig->isHandmatig());
        $this->assertSame(6, $handmatig->metingen()->count());

        // Elk handmatig meetpunt staat op naam van wie het invoerde; de
        // berekende meetpunten juist niet.
        $this->assertSame(0, $handmatig->metingen()->whereNull('ingevoerd_door_id')->count());
        $this->assertSame(
            0,
            Meting::whereNotNull('ingevoerd_door_id')
                ->where('kpi_definitie_id', '!=', $handmatig->id)
                ->count(),
            'Een door isms:meet-kpis vastgelegd meetpunt hoort geen invoerder te hebben.',
        );

        // De klikratio daalt over de reeks: dat is de bedoeling bij richting
        // omlaag, en het maakt het richting-onderscheid in de demo zichtbaar.
        $reeks = $handmatig->metingen()->orderBy('gemeten_op')->get();
        $this->assertSame('omlaag', $handmatig->richting);
        $this->assertGreaterThan($reeks->last()->percentage(), $reeks->first()->percentage());
    }
}
