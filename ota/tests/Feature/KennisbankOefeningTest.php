<?php

namespace Tests\Feature;

use App\Livewire\AuditProgrammaBeheer;
use App\Livewire\AuditrondeDetail;
use App\Livewire\AuditsOverzicht;
use App\Livewire\IncidentenOverzicht;
use App\Livewire\MeetaanpakOverzicht;
use App\Livewire\RisicoDetail;
use App\Models\Auditprogramma;
use App\Models\AuditprogrammaDekking;
use App\Models\Auditronde;
use App\Models\Gebruiker;
use App\Models\Risico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * Bij elke oefening in de kennisbank hoort een opdracht voor een AI-assistent, als
 * los bestand in `resources/kennisbank/opdrachten/`. Die opdracht beweert
 * tientallen dingen over EzISMS: foutmeldingen, standaardwaarden, knoppen, statuslabels en wat een
 * scherm níét kan. Een taalmodel controleert die beweringen niet — het herhaalt ze
 * met overtuiging.
 *
 * Daarom deze test. Hij toetst niet of de applicatie goed werkt (dat doen de
 * suites van de blokken zelf), maar of de **tekst nog over deze applicatie gaat**.
 * Verandert een foutmelding in het scherm, dan valt hier iets om en niet een CISO
 * die de oefening doet.
 *
 * De regel erachter: elk feit dat een oefening letterlijk citeert, staat hier met
 * de bron erbij. Wie een melding herformuleert, komt hier langs en werkt de
 * oefening bij.
 */
class KennisbankOefeningTest extends TestCase
{
    use RefreshDatabase;

    private const KPI_OPDRACHT = 'resources/kennisbank/opdrachten/kpi-oefening.md';

    private const AUDIT_OPDRACHT = 'resources/kennisbank/opdrachten/audit-oefening.md';

    private const RISICO_OPDRACHT = 'resources/kennisbank/opdrachten/risico-oefening.md';

    /** Slug => de slotkop van het gouden pad, als bewijs dat de opdracht compleet is. */
    private const OEFENINGEN = [
        'kpi-oefening' => '### 14 — Het einde',
        'audit-oefening' => '### 14 — Wat krijgt de auditor te zien?',
        'risico-oefening' => '### 10 — Wat krijgt de auditor te zien?',
    ];

    /**
     * Citaat => het bestand waaruit het komt. De naald moet in beide voorkomen;
     * losse fragmenten waar de code een melding aan elkaar plakt, zijn bewust
     * kort gehouden tot binnen één string in de bron.
     *
     * @var array<string, string>
     */
    private const KPI_CITATEN = [
        // Meldingen uit het KPI-beheer (12e).
        'Bij een ratio kan de teller niet groter zijn dan de noemer.' => 'app/Livewire/MeetaanpakOverzicht.php',
        'Bij richting omlaag hoort de signaalwaarde bóven de streefwaarde te liggen.' => 'app/Livewire/MeetaanpakOverzicht.php',
        'Bij richting omhoog hoort de signaalwaarde ónder de streefwaarde te liggen.' => 'app/Livewire/MeetaanpakOverzicht.php',
        'Een meting is onveranderlijk; een correctie is een' => 'app/Livewire/MeetaanpakOverzicht.php',
        'De definitieversie staat nu op v' => 'app/Livewire/MeetaanpakOverzicht.php',
        'omdat de betekenis van de reeks veranderde.' => 'app/Livewire/MeetaanpakOverzicht.php',
        // Statuslabels van de semafoor (12d).
        'Streefwaarde gehaald' => 'app/Support/Kpitrend.php',
        'Streefwaarde niet gehaald' => 'app/Support/Kpitrend.php',
        'Voorbij de signaalwaarde' => 'app/Support/Kpitrend.php',
        'Geen streefwaarde vastgesteld' => 'app/Support/Kpitrend.php',
        // Het dashboardsignaal bij een KPI die niemand meer invult (12e §5).
        'handmatige KPI is stilgevallen' => 'app/Support/Dashboardsignalen.php',
        // Veldlabels en knoppen die de oefening als schermtekst opvoert.
        'Handmatig — ik voer teller en noemer zelf in' => 'resources/views/livewire/meetaanpak-overzicht.blade.php',
        'Ratio (percentage)' => 'resources/views/livewire/meetaanpak-overzicht.blade.php',
        'Dagen (gemiddelde)' => 'resources/views/livewire/meetaanpak-overzicht.blade.php',
        'Aantal (telling)' => 'resources/views/livewire/meetaanpak-overzicht.blade.php',
        'Omhoog — hoger is beter' => 'resources/views/livewire/meetaanpak-overzicht.blade.php',
        'Omlaag — lager is beter' => 'resources/views/livewire/meetaanpak-overzicht.blade.php',
        'Meetpunt invoeren' => 'resources/views/livewire/meetaanpak-overzicht.blade.php',
        'Streefwaarde vaststellen' => 'resources/views/livewire/meetaanpak-overzicht.blade.php',
        // De enige ingebouwde incidentbron, die de oefening als afwijking opvoert.
        'Incidenten: tijdig extern gemeld / alle meldingen met een termijn' => 'app/Support/Meetbronnen.php',
    ];

    /** @var array<string, string> */
    private const AUDIT_CITATEN = [
        // Weigeringen en verplichte velden in het rondedossier (11d).
        'Geef aan waarom dit object niet is behandeld.' => 'app/Livewire/AuditrondeDetail.php',
        'Noteer wat er met deze bevinding is gebeurd.' => 'app/Livewire/AuditrondeDetail.php',
        'Ronde telt niet mee voor de dekkingsmatrix; hij blijft wel volledig in het dossier.' => 'app/Livewire/AuditrondeDetail.php',
        'Geen gesprek — eigen waarneming' => 'app/Livewire/AuditrondeDetail.php',
        'niet aan toegekomen' => 'app/Livewire/AuditrondeDetail.php',
        // De twee blokkades bij het sluiten van een non-conformiteit.
        'Start eerst een non-conformiteit (afwijking) voor deze bevinding.' => 'app/Models/Bevinding.php',
        'De gekoppelde afwijking is nog niet gesloten.' => 'app/Models/Bevinding.php',
        // Het type dat de nulmeting draagt.
        'Interne nulmeting' => 'app/Models/Auditronde.php',
        // Het programmascherm: de twee velden en de twee knoppen waar de oefening op leunt.
        'De certificaatdatum is het natuurlijke anker.' => 'resources/views/livewire/audit-programma-beheer.blade.php',
        'Voorbereiding = de aanloop naar certificering' => 'resources/views/livewire/audit-programma-beheer.blade.php',
        'Vul standaard (eenmaal per cyclus)' => 'resources/views/livewire/audit-programma-beheer.blade.php',
        'Verdeel de groepen over de jaren' => 'resources/views/livewire/audit-programma-beheer.blade.php',
        // Het jaartal als label — de reden dat meerdere plannen per jaar mogen.
        'Het jaartal is een label; meerdere plannen in hetzelfde jaar mogen' => 'resources/views/livewire/audits-overzicht.blade.php',
        // Jaarplannen toevoegen vanuit het programma, ook bij een programma van één jaar.
        'toegevoegd als programmajaar' => 'app/Livewire/AuditProgrammaBeheer.php',
        'programmajaren hebben een jaarplan.' => 'resources/views/livewire/audit-programma-beheer.blade.php',
        // Onafhankelijkheid en het bevriezen bij afronden.
        'De toegewezen auditor start de uitvoering.' => 'resources/views/livewire/auditronde-detail.blade.php',
        'Afronden bevriest de bevindingen en de behandelingen. Doorgaan?' => 'resources/views/livewire/auditronde-detail.blade.php',
        'De ronde is afgerond; de bevindingen zijn bevroren.' => 'resources/views/livewire/auditronde-detail.blade.php',
        'Een gesloten bevinding is definitief en kan niet heropend worden.' => 'resources/views/livewire/auditronde-detail.blade.php',
        "Zonder bron is 'geen opmerkingen' een bewering" => 'resources/views/livewire/auditronde-detail.blade.php',
        'Waarom is dit object niet behandeld?' => 'resources/views/livewire/auditronde-detail.blade.php',
        // De matrix: scope is geen dekking.
        'Alleen afgeronde rondes tellen als dekking.' => 'resources/views/livewire/dekkingsmatrix.blade.php',
        'objecten zonder afgeronde dekking in deze cyclus' => 'resources/views/livewire/dekkingsmatrix.blade.php',
        'Een uitgevoerde ronde dekt alleen wat zij behandelde' => 'resources/views/livewire/dekkingsmatrix.blade.php',
    ];

    /** @var array<string, string> */
    private const RISICO_CITATEN = [
        // Het risicodetail: de score is afgeleid, de acceptatie is niet van de CISO.
        'De risicoscore wordt berekend als kans x impact en is niet handmatig te zetten.' => 'resources/views/livewire/risico-detail.blade.php',
        'Risicoscore (Berekend, drempel =' => 'resources/views/livewire/risico-detail.blade.php',
        'Dit restrisico ligt boven de acceptatiedrempel' => 'resources/views/livewire/risico-detail.blade.php',
        'Het plan is hiermee vastgelegd, maar het risico geldt pas als geaccepteerd zodra de directie tekent.' => 'resources/views/livewire/risico-detail.blade.php',
        'Het restrisico accepteren? Dit wordt vastgelegd in de audit trail.' => 'resources/views/livewire/risico-detail.blade.php',
        'De status volgt normaal automatisch uit beoordeling en behandelplan' => 'resources/views/livewire/risico-detail.blade.php',
        'Uit welke §4.1-kwestie(s) is dit risico voortgekomen?' => 'resources/views/livewire/risico-detail.blade.php',
        'vereisen een expliciete acceptatie: vul in wie accepteert.' => 'app/Livewire/RisicoDetail.php',
        // Het register.
        'Na toevoegen open je meteen het detailscherm om kans en impact te bepalen.' => 'resources/views/livewire/risicos-overzicht.blade.php',
        'Alleen boven de drempel' => 'resources/views/livewire/risicos-overzicht.blade.php',
        'Behandelplan opgesteld' => 'app/Livewire/RisicosOverzicht.php',
        // De trend: onveranderlijk, en de toelichting draagt de reden van de beweging.
        'de jaarsnapshots worden onveranderlijk vastgelegd en nooit herrekend.' => 'resources/views/livewire/restrisico-trend.blade.php',
        "Per control het hoogste netto-restrisico van de gekoppelde risico's, per peiljaar." => 'resources/views/livewire/restrisico-trend.blade.php',
        'Leg de reden van de beweging vast (gemitigeerd, herscoord, risico afgevoerd).' => 'resources/views/livewire/restrisico-trend.blade.php',
        'Bijv. R-7 gemitigeerd na invoering MFA.' => 'resources/views/livewire/restrisico-trend.blade.php',
        'Een trend heeft minstens twee peiljaren nodig.' => 'resources/views/livewire/restrisico-trend.blade.php',
        // De snapshot zelf.
        'Er bestaat al een restrisico-snapshot voor' => 'app/Console/Commands/LegRestrisicoVast.php',
        // Het dashboardsignaal boven de drempel.
        'Boven die grens hoort de directie het restrisico te accepteren.' => 'app/Support/Dashboardsignalen.php',
        // De niveaudefinities staan voluit in de opdracht, want beslispunt 2 vraagt
        // de cursist zijn cijfer daartegen te motiveren. Ze komen uit de seedbron
        // van de risicocriteria; de naalden blijven binnen één PHP-string.
        'Schat hoe vaak het scenario zich voordoet als je' => 'config/beoordelingsschaal.php',
        'Doet zich meerdere keren per jaar voor, of de omstandigheden die' => 'config/beoordelingsschaal.php',
        'Ordegrootte: elk kwartaal.' => 'config/beoordelingsschaal.php',
        'Een kernproces ligt langdurig stil, of gegevens van een' => 'config/beoordelingsschaal.php',
        'Uitgaan van "het gebeurt" is realistischer dan van een kans.' => 'config/beoordelingsschaal.php',
    ];

    public function test_de_kpi_oefening_citeert_bestaande_schermteksten(): void
    {
        $this->toetsCitaten(self::KPI_OPDRACHT, self::KPI_CITATEN);
    }

    public function test_de_auditoefening_citeert_bestaande_schermteksten(): void
    {
        $this->toetsCitaten(self::AUDIT_OPDRACHT, self::AUDIT_CITATEN);
    }

    public function test_de_risico_oefening_citeert_bestaande_schermteksten(): void
    {
        $this->toetsCitaten(self::RISICO_OPDRACHT, self::RISICO_CITATEN);
    }

    /**
     * De hele reeks in de risico-oefening (16 – 12 – 10 – 15) hangt aan deze twee
     * grenzen: 16 is rood, 12 en 10 zijn amber, 6 is groen. Verschuiven ze, dan
     * kloppen de kleuren in de opdracht niet meer.
     */
    public function test_de_drempels_in_de_risico_oefening_kloppen(): void
    {
        $this->assertSame(15, Risico::DREMPEL_STANDAARD);
        $this->assertSame(10, Risico::WAARSCHUWINGSDREMPEL_STANDAARD);
        $this->assertSame('mitigeren', (new RisicoDetail)->behandeloptie);

        $oefening = $this->tekst(self::RISICO_OPDRACHT);
        $this->assertStringContainsString('acceptatiedrempel 15, waarschuwingsgrens 10', $oefening);
    }

    /**
     * Beslispunt 6 leert dat een gemist peiljaar niet is in te halen. Dat klopt
     * alleen zolang de snapshot van een geplande jaartaak komt en niet van een
     * scherm: er is geen knop die hem alsnog voor een oud jaar aanmaakt.
     */
    public function test_de_jaarsnapshot_komt_van_een_geplande_taak(): void
    {
        $planning = self::eenRegel(file_get_contents(base_path('routes/console.php')));

        $this->assertStringContainsString("Schedule::command('isms:leg-restrisico-vast')", $planning);
        $this->assertStringContainsString("yearlyOn(12, 31, '23:00')", $planning);

        $oefening = $this->tekst(self::RISICO_OPDRACHT);
        $this->assertStringContainsString('elk jaar op 31 december om 23:00', $oefening);
    }

    /**
     * De twee standaarden waar de KPI-oefening op leunt: wie ze laat staan,
     * kiest ongemerkt ratio en omhoog. Zou het formulier morgen op 'aantal' en
     * 'omlaag' openen, dan oefent de cursist een val die niet meer bestaat.
     */
    public function test_de_standaarden_van_het_kpi_formulier_kloppen(): void
    {
        $formulier = new MeetaanpakOverzicht;

        $this->assertSame('ratio', $formulier->eenheid);
        $this->assertSame('omhoog', $formulier->richting);
        $this->assertSame('check', $formulier->fase);
        $this->assertSame('', $formulier->meetbron, 'Leeg = handmatig; de oefening noemt dat de standaard.');

        $oefening = $this->tekst(self::KPI_OPDRACHT);
        $this->assertStringContainsString('| Eenheid | *Ratio (percentage)*', $oefening);
        $this->assertStringContainsString('| **Ratio** |', $oefening);
        $this->assertStringContainsString('| **Omhoog** |', $oefening);
    }

    /**
     * Beslispunt 3 en 4 van de auditoefening draaien om deze standaarden: het
     * programmaformulier opent op de driejarige certificeringscyclus, en een
     * nieuwe ronde op 'intern'. Wie niets aanpast, krijgt dus precies niet wat de
     * opstartfase nodig heeft.
     */
    public function test_de_standaarden_van_de_auditschermen_kloppen(): void
    {
        $programma = new AuditProgrammaBeheer;
        $this->assertSame('certificeringscyclus', $programma->aard);
        $this->assertSame('3', $programma->aantalJaren);

        $overzicht = new AuditsOverzicht;
        $this->assertSame('intern', $overzicht->rondeType);

        $dossier = new AuditrondeDetail;
        $this->assertSame('geen_opmerkingen', $dossier->behandelAfhandeling);
        $this->assertSame('observatie', $dossier->bevindingType);

        $oefening = $this->tekst(self::AUDIT_OPDRACHT);
        $this->assertStringContainsString('| Aantal jaren | geheel getal van 1 tot en met 6 | **3** |', $oefening);
        $this->assertStringContainsString('| **Certificeringscyclus** |', $oefening);
    }

    /**
     * De oefening leert twee dingen over de afhandeling per object: er zijn maar
     * twee standen die een auditor zelf zet, en 'bevinding' is er géén — die is
     * afgeleid en mag niet als apart feit bestaan (11d §2).
     */
    public function test_de_afhandelingen_van_een_auditobject_kloppen(): void
    {
        $this->assertSame(
            ['niet_behandeld', 'geen_opmerkingen', 'niet_toegekomen'],
            Auditronde::AFHANDELINGEN
        );

        $this->assertNotContains('bevinding', Auditronde::AFHANDELINGEN);

        $this->assertSame(['intern', 'intern_nulmeting'], Auditronde::INTERNE_TYPEN);
    }

    /**
     * Het scharnierpunt van de hele auditoefening: een nulmeting telt niet mee
     * voor de dekking, en dat gaat vanzelf. Zou die automaat verdwijnen, dan
     * klopt beslispunt 4 niet meer.
     */
    public function test_een_nulmeting_telt_niet_mee_voor_de_dekking(): void
    {
        $nulmeting = Auditronde::factory()->create(['type' => 'intern_nulmeting']);
        $gewoon = Auditronde::factory()->create(['type' => 'intern']);

        $this->assertFalse($nulmeting->telt_mee_voor_dekking);
        $this->assertTrue($gewoon->telt_mee_voor_dekking);
    }

    /**
     * Beslispunt 9 en 13 van de auditoefening leunen op twee eigenschappen die
     * nergens als tekst op een scherm staan:
     *
     * - een jaarplan krijgt het jaartal waarin zijn programmajaar begint, zodat
     *   programmajaar 1 van een cyclus die op 15 december 2027 start het label
     *   2027 draagt;
     * - de matrix leest "gepland" uit de huidige dekkingsregel, zodat een
     *   verschoven startjaar een gat uit een voorbij jaar laat verdwijnen.
     */
    public function test_de_matrixfeiten_van_de_auditoefening_kloppen(): void
    {
        $programma = new Auditprogramma([
            'start_datum' => '2027-12-15',
            'aantal_jaren' => 3,
        ]);
        $jaren = $programma->programmajaren();
        $this->assertSame(2027, $jaren[0]['start']->year);
        $this->assertSame(2028, $jaren[1]['start']->year);

        $this->assertStringContainsString(
            "'jaar' => \$vrij['start']->year,",
            file_get_contents(app_path('Livewire/AuditProgrammaBeheer.php'))
        );

        $dekking = new AuditprogrammaDekking(['interval_jaren' => 3, 'gepland_start_programmajaar' => 2]);
        $this->assertSame([2], $dekking->geplandeProgrammajaren(3), 'Een regel die in jaar 2 begint, plant jaar 1 niet.');

        $jaarlijks = new AuditprogrammaDekking(['interval_jaren' => 1, 'gepland_start_programmajaar' => 1]);
        $this->assertSame([1, 2, 3], $jaarlijks->geplandeProgrammajaren(3), 'Jaarlijks vanaf jaar 1 houdt jaar 1 gepland.');

        $opdracht = $this->tekst(self::AUDIT_OPDRACHT);
        $this->assertStringContainsString('| 1 | 15-12-2027 t/m 14-12-2028 | **2027** |', $opdracht);
        $this->assertStringContainsString('**jaarlijks, vanaf jaar 1**', $opdracht);
    }

    /**
     * Beslispunt 8 van de KPI-oefening valt of staat hiermee: de telling kan niet
     * machinaal, want het register kent geen type en geen zoekveld. Komt er ooit
     * een categorieveld of een zoekterm bij, dan is de kern van dat beslispunt
     * achterhaald.
     */
    public function test_het_incidentregister_kent_geen_categorie_en_geen_zoekveld(): void
    {
        $this->assertFalse(
            Schema::hasColumn('incidenten', 'categorie'),
            'De oefening leert dat incidenten geen categorieveld hebben.'
        );

        $this->assertTrue(Schema::hasColumn('incidenten', 'gemeld_op'));
        $this->assertFalse(
            Schema::hasColumn('incidenten', 'gebeurtenis_op'),
            'De oefening leert dat er alleen een datumstempel is, geen gebeurtenisdatum.'
        );

        $eigenschappen = array_map(
            fn ($eigenschap) => $eigenschap->getName(),
            (new ReflectionClass(IncidentenOverzicht::class))->getProperties(\ReflectionProperty::IS_PUBLIC)
        );

        foreach ($eigenschappen as $naam) {
            $this->assertDoesNotMatchRegularExpression(
                '/zoek|term|search/i',
                $naam,
                "Het incidentregister heeft er een zoekveld bij ({$naam}); beslispunt 8 van de oefening gaat ervan uit dat dat er niet is."
            );
        }
    }

    /**
     * Eén vangnet onder de citaten hierboven: als een opdracht leeg of afgekapt
     * is, slagen ze allemaal op een lege tekst. En de drie opdrachten horen
     * dezelfde commando's te kennen — `cheat` is er om ze snel te controleren,
     * en een oefening waar hij ontbreekt, is precies de oefening die dan niet
     * gecontroleerd wordt.
     */
    public function test_de_opdrachten_zijn_compleet_en_gelijk_van_opzet(): void
    {
        foreach (self::OEFENINGEN as $slug => $slotkop) {
            $opdracht = $this->tekst("resources/kennisbank/opdrachten/{$slug}.md");

            $this->assertStringContainsString('## 3. Feiten over EzISMS', $opdracht, $slug);
            $this->assertStringContainsString('## 5. Het gouden pad', $opdracht, $slug);
            $this->assertStringContainsString($slotkop, $opdracht, $slug);

            foreach (['`hint`', '`doorzetten`', '`terug`', '`stand`', '`ga naar N`', '`cheat`', '`stop`'] as $commando) {
                $this->assertStringContainsString($commando, $opdracht, "{$slug} mist het commando {$commando}.");
            }

            $this->assertStringContainsString('*overgeslagen met cheat*', $opdracht, $slug);
        }
    }

    /**
     * Het artikel legt uit en linkt; de opdracht zelf staat er niet meer in. Staat
     * hij er tóch weer in, dan zijn er twee kopieën die uit elkaar gaan lopen, en
     * bewaakt deze test er maar één van.
     */
    public function test_elk_artikel_linkt_naar_zijn_opdracht_en_bevat_hem_niet(): void
    {
        foreach (array_keys(self::OEFENINGEN) as $slug) {
            $artikel = $this->tekst("resources/kennisbank/{$slug}.md");

            $this->assertStringContainsString("(/kennisbank/{$slug}/opdracht)", $artikel, $slug);
            $this->assertStringNotContainsString('## 3. Feiten over EzISMS', $artikel, $slug);
            $this->assertStringContainsString('`cheat`', $artikel, "{$slug} noemt cheat niet in de inleiding.");
        }

        // En zo komt hij op de pagina: een gewone link, zodat de browser het
        // bestand downloadt in plaats van dat Livewire probeert te navigeren.
        $this->actingAs(Gebruiker::factory()->create())
            ->get('/kennisbank/risico-oefening')
            ->assertOk()
            ->assertSee('<a href="/kennisbank/risico-oefening/opdracht">', false);
    }

    /**
     * Elke ingelogde gebruiker krijgt de opdracht als bestand — ook zonder rol
     * CISO, want het artikel waar de tekst eerst in stond, mocht iedereen lezen.
     */
    public function test_een_ingelogde_gebruiker_downloadt_de_opdracht(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        foreach (array_keys(self::OEFENINGEN) as $slug) {
            $antwoord = $this->actingAs($gebruiker)->get("/kennisbank/{$slug}/opdracht");

            $antwoord->assertOk()->assertDownload("{$slug}-opdracht.md");
            $this->assertStringStartsWith('text/markdown', $antwoord->headers->get('Content-Type'));
            $this->assertSame(
                file_get_contents(resource_path("kennisbank/opdrachten/{$slug}.md")),
                $antwoord->streamedContent()
            );
        }
    }

    public function test_een_artikel_zonder_opdracht_geeft_geen_download(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/kpi-opzetten-voorbeeld/opdracht')->assertNotFound();
        $this->actingAs($gebruiker)->get('/kennisbank/bestaat-niet/opdracht')->assertNotFound();
    }

    public function test_een_gast_krijgt_de_opdracht_niet(): void
    {
        $this->get('/kennisbank/kpi-oefening/opdracht')->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, string>  $citaten
     */
    private function toetsCitaten(string $opdracht, array $citaten): void
    {
        $oefening = $this->tekst($opdracht);

        foreach ($citaten as $naald => $bron) {
            $this->assertStringContainsString(
                $naald,
                $oefening,
                "{$opdracht} citeert '{$naald}' niet meer; is de tekst herschreven?"
            );

            $this->assertStringContainsString(
                $naald,
                self::eenRegel(file_get_contents(base_path($bron))),
                "{$opdracht} citeert '{$naald}', maar die tekst staat niet meer in {$bron}."
            );
        }
    }

    private function tekst(string $pad): string
    {
        $volledig = base_path($pad);

        $this->assertFileExists($volledig);

        return self::eenRegel(file_get_contents($volledig));
    }

    /**
     * Alle witruimte tot één spatie. Zonder dit valt een citaat om zodra de
     * regelafbreking in de markdown of in de PHP-string een woord verderop komt
     * te liggen — een verschil dat niemand iets zegt.
     */
    private static function eenRegel(string $tekst): string
    {
        return (string) preg_replace('/\s+/u', ' ', $tekst);
    }
}
