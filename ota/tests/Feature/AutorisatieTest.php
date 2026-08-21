<?php

namespace Tests\Feature;

use App\Models\Gebruiker;
use App\Support\Recordscope;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Routedefinitie;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AutorisatieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);
    }

    public function test_ciso_heeft_muteerrecht_op_identity_access(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        $this->assertTrue(Gate::forUser($ciso)->allows('heeft-niveau', ['identity-access', 'muteren']));
    }

    public function test_muteerrecht_impliceert_leesrecht(): void
    {
        // De rechtenmatrix geeft de CISO alleen 'muteren'. Omdat de niveaus een
        // ladder zijn, moet dat ook leestoegang geven — anders ziet de CISO geen
        // enkel menu-item en krijgt hij 403 op elke leespagina.
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        $this->assertTrue(Gate::forUser($ciso)->allows('heeft-niveau', ['identity-access', 'lezen']));
        $this->assertTrue(Gate::forUser($ciso)->allows('heeft-niveau', ['context-scope', 'lezen']));
    }

    public function test_ciso_kan_de_leespaginas_daadwerkelijk_openen(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        $this->actingAs($ciso)->get('/gebruikers')->assertOk();
        $this->actingAs($ciso)->get('/scope')->assertOk();
    }

    public function test_auditor_heeft_leesrecht_maar_geen_muteerrecht(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->assertTrue(Gate::forUser($auditor)->allows('heeft-niveau', ['identity-access', 'lezen']));
        $this->assertFalse(Gate::forUser($auditor)->allows('heeft-niveau', ['identity-access', 'muteren']));
    }

    // --- Management en het niveau `goedkeuren` (implementatie/01c) ----------

    public function test_goedkeuren_impliceert_lezen_maar_geen_muteren(): void
    {
        // De kern van 01c: vaststellen is een andere sóórt bevoegdheid dan
        // bewerken. Stond `goedkeuren` nog bovenaan de ladder, dan zou de
        // directie het hele risicoregister kunnen herschrijven.
        $management = Gebruiker::factory()->metRol('Management')->create();

        $this->assertTrue(Gate::forUser($management)->allows('heeft-niveau', ['risico-soa', 'goedkeuren']));
        $this->assertTrue(Gate::forUser($management)->allows('heeft-niveau', ['risico-soa', 'lezen']));
        $this->assertFalse(Gate::forUser($management)->allows('heeft-niveau', ['risico-soa', 'muteren']));
        $this->assertFalse(Gate::forUser($management)->allows('heeft-niveau', ['risico-soa', 'uitvoeren']));
    }

    public function test_muteerrecht_impliceert_geen_goedkeurrecht_meer(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        $this->assertTrue(Gate::forUser($ciso)->allows('heeft-niveau', ['risico-soa', 'muteren']));
        $this->assertFalse(Gate::forUser($ciso)->allows('heeft-niveau', ['risico-soa', 'goedkeuren']));
    }

    public function test_management_heeft_geen_toegang_tot_gebruikersbeheer(): void
    {
        // Bewust géén rij in de rechtenmatrix: dat is de reden dat de rol bestaat.
        $management = Gebruiker::factory()->metRol('Management')->create();

        $this->assertFalse(Gate::forUser($management)->allows('heeft-niveau', ['identity-access', 'lezen']));

        $this->actingAs($management)->get('/gebruikers')->assertForbidden();
        $this->actingAs($management)->get('/dashboard')->assertOk()->assertDontSee('Gebruikers');
    }

    public function test_management_opent_de_leespaginas_van_zijn_blokken(): void
    {
        $management = Gebruiker::factory()->metRol('Management')->create();

        foreach (['/scope', '/assets', '/risicos', '/soa', '/beleid', '/incidenten',
            '/leveranciers', '/audits', '/management-review', '/taken'] as $pad) {
            $this->actingAs($management)->get($pad)->assertOk();
        }
    }

    public function test_management_ziet_alle_incidenten_maar_alleen_eigen_taken(): void
    {
        // Record-scoping: `exporteren` op incidenten geeft volledige inzage
        // (§9.3-input), `uitvoeren` op taken houdt het bij de eigen rijen.
        $management = Gebruiker::factory()->metRol('Management')->create();

        $this->actingAs($management);

        $this->assertTrue(Recordscope::magAllesZien('incident-afwijkingenbeheer'));
        $this->assertTrue(Recordscope::magAllesZien('bewijsrepository-audit-trail'));
        $this->assertTrue(Recordscope::magAllesZien('beleid-maatregelbeheer'));
        $this->assertFalse(Recordscope::magAllesZien('taken-workflow-engine'));
        $this->assertFalse(Recordscope::magAllesZien('bewustzijn-training'));
    }

    public function test_rollen_zijn_cumulatief(): void
    {
        // Het model dwingt geen functiescheiding af (01c §6): bij een kleine
        // organisatie kan één persoon beide petten dragen. Dat hoort zichtbaar
        // te zijn, niet onmogelijk.
        $beide = Gebruiker::factory()->metRol('CISO')->metRol('Management')->create();

        $this->assertTrue(Gate::forUser($beide)->allows('heeft-niveau', ['beleid-maatregelbeheer', 'muteren']));
        $this->assertTrue(Gate::forUser($beide)->allows('heeft-niveau', ['beleid-maatregelbeheer', 'goedkeuren']));
    }

    public function test_medewerker_heeft_geen_toegang_tot_risico_soa(): void
    {
        // Er staat bewust geen rij voor deze combinatie in de rechtenmatrix.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->assertFalse(Gate::forUser($medewerker)->allows('heeft-niveau', ['risico-soa', 'lezen']));
    }

    public function test_gebruiker_zonder_rol_heeft_nergens_toegang_toe(): void
    {
        $zonderRol = Gebruiker::factory()->create();

        $this->assertFalse(Gate::forUser($zonderRol)->allows('heeft-niveau', ['identity-access', 'lezen']));
    }

    public function test_medewerker_mag_het_gebruikersoverzicht_bekijken(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)->get('/gebruikers')->assertOk();
    }

    public function test_gebruiker_zonder_leesrecht_wordt_geweerd_van_gebruikersoverzicht(): void
    {
        $zonderRol = Gebruiker::factory()->create();

        $this->actingAs($zonderRol)->get('/gebruikers')->assertForbidden();
    }

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $this->get('/gebruikers')->assertRedirect('/login');
    }

    public function test_dashboard_toont_alleen_blokken_waar_de_rol_bij_mag(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/dashboard')
            ->assertOk()
            ->assertSee('Gebruikers');
    }

    public function test_instellingenpaginas_zijn_voor_elke_rol_bereikbaar(): void
    {
        // Eigen profiel is bewust geen blok-permissie (implementatie/05 §4).
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)->get('/settings/profile')
            ->assertOk()
            ->assertSee($medewerker->email);

        $this->actingAs($medewerker)->get('/settings/password')->assertOk();
        $this->actingAs($medewerker)->get('/settings/appearance')->assertOk();
    }

    public function test_dashboard_van_gebruiker_zonder_rol_toont_geen_menu_items(): void
    {
        $zonderRol = Gebruiker::factory()->create();

        $this->actingAs($zonderRol)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Gebruikers');
    }

    // --- De rechtenmatrix per scherm (implementatie/00f §4) -----------------

    private const ROLLEN = ['CISO', 'Auditor', 'Management', 'Medewerker', 'Administrator'];

    /**
     * Wat elke rol op elk scherm zónder routeparameter krijgt: 200 opent, 403
     * weert. Dit stond tot 03-08-2026 als losse `test_medewerker_krijgt_geen_toegang`
     * in zestien testbestanden, en juist dáár was niet te zien welke schermen
     * ontbraken — de helft.
     *
     * Drie soorten uitkomst zitten erin door elkaar en dat hoort zo: het niveau
     * dat de route eist (`routes/web.php`), de rechtenmatrix
     * (`RolPermissieSeeder`) en de record-scope die sommige schermen in `mount()`
     * afdwingen — wie alleen zijn eigen rijen ziet, komt op een overzichtsscherm
     * niets doen.
     *
     * **Eén rij verdient aandacht:** de Auditor krijgt 403 op /bewijsstukken,
     * /taken, /beleid, /incidenten en /mijn-trainingen. Die schermen staan op
     * `uitvoeren` omdat de Medewerker er meldt, uploadt of bevestigt, en
     * `exporteren` klimt de ladder niet op. In de componenttests ziet dezelfde
     * Auditor daar juist álle rijen. Deze tabel legt vast wat het systeem
     * vandaag doet, niet wat het zou moeten doen; dat laatste is een besluit en
     * hangt samen met de openstaande vraag of de Auditor `uitvoeren` hoort te
     * krijgen.
     */
    private const SCHERMEN = [
        //                                          CISO Auditor Management Medewerker Administrator
        '/afwijkingen' => [200, 403, 403, 403, 403],
        '/assets' => [200, 200, 200, 200, 403],
        '/audit-log' => [200, 200, 200, 403, 403],
        '/audits' => [200, 200, 200, 403, 403],
        '/audits/bevindingen' => [200, 200, 200, 403, 403],
        '/audits/dekking' => [200, 200, 200, 403, 403],
        '/audits/programma' => [200, 200, 200, 403, 403],
        // De twee schermen waar de kolommen omdraaien: alleen de Administrator
        // komt hier binnen, en hij komt nergens anders binnen (01e §2.2, §3).
        '/beheer/export' => [403, 403, 403, 403, 200],
        '/beheer/toetsen' => [403, 403, 403, 403, 200],
        '/belanghebbenden' => [200, 200, 200, 200, 403],
        '/beleid' => [200, 403, 200, 200, 403],
        '/bewijsstukken' => [200, 403, 200, 200, 403],
        // 200 voor de Administrator, en dat is geen gat: het dashboard heeft
        // bewust geen autorisatiecheck op blokniveau (elk paneel checkt zijn
        // eigen blok), dus hij krijgt een leeg scherm. `/` stuurt hem daar niet
        // heen — zie AdministratorTest (01e §2.3).
        '/dashboard' => [200, 200, 200, 200, 200],
        '/doelgroepen' => [200, 200, 403, 403, 403],
        '/gebruikers' => [200, 200, 403, 200, 403],
        '/incidenten' => [200, 403, 200, 200, 403],
        '/integraties' => [200, 200, 200, 403, 403],
        '/issues' => [200, 200, 200, 200, 403],
        '/leveranciers' => [200, 200, 200, 403, 403],
        '/management-review' => [200, 200, 200, 403, 403],
        '/meetaanpak' => [200, 200, 200, 403, 403],
        '/mijn-trainingen' => [200, 403, 200, 200, 403],
        '/notificaties' => [200, 200, 200, 403, 403],
        '/organisatie-eenheden' => [200, 200, 200, 200, 403],
        '/risicos' => [200, 200, 200, 403, 403],
        '/risicos/criteria' => [200, 200, 200, 403, 403],
        '/risicos/matrix' => [200, 200, 200, 403, 403],
        '/schermkopieen' => [200, 200, 200, 403, 403],
        '/scope' => [200, 200, 200, 200, 403],
        '/soa' => [200, 200, 200, 403, 403],
        '/soa/restrisico-trend' => [200, 200, 200, 403, 403],
        '/systemen' => [200, 200, 200, 200, 403],
        '/taaksjablonen' => [200, 403, 403, 403, 403],
        '/taken' => [200, 403, 200, 200, 403],
        '/toetsen/bouwhulp' => [200, 403, 403, 403, 403],
        '/toetsen/bouwhulp/onquizvoltooid.js' => [200, 403, 403, 403, 403],
        '/toetsen/bouwhulp/skelet.html' => [200, 403, 403, 403, 403],
        '/toetsen/resultaten' => [200, 200, 403, 403, 403],
        '/toetsen/uitzetten' => [200, 403, 403, 403, 403],
        '/trainingen' => [200, 200, 403, 403, 403],
        // Blok 15. Het register staat op 'lezen' en niet op 'uitvoeren': anders
        // dan bij taken is inzage in álle wijzigingen juist de bedoeling
        // (implementatie/15 §5). De Medewerker komt er dus wél op.
        // De Administrator staat naast de ISMS-rollen en heeft op geen enkel
        // ISMS-blok een rij; die krijgt hier dus 403, net als overal.
        '/wijzigingen' => [200, 200, 200, 200, 403],
        '/wijzigingssjablonen' => [200, 403, 403, 403, 403],
    ];

    public function test_elke_rol_komt_precies_op_de_schermen_waar_hij_hoort(): void
    {
        $gebruikers = [];
        foreach (self::ROLLEN as $rol) {
            $gebruikers[$rol] = Gebruiker::factory()->metRol($rol)->create();
        }

        $werkelijk = [];
        foreach (array_keys(self::SCHERMEN) as $pad) {
            $werkelijk[$pad] = array_map(
                fn (string $rol) => $this->actingAs($gebruikers[$rol])->get($pad)->getStatusCode(),
                self::ROLLEN,
            );
        }

        // In één keer, niet per cel: bij een rechtenwijziging wil je alle
        // gevolgen naast elkaar zien en niet de eerste die stukloopt.
        $this->assertSame(self::SCHERMEN, $werkelijk);
    }

    /**
     * De tabel hierboven is alleen iets waard als hij compleet blijft. Een nieuw
     * scherm zonder rij faalt hier — en dat is precies het gat dat de zestien
     * losse tests jarenlang openlieten.
     */
    public function test_de_matrix_dekt_elk_scherm_zonder_routeparameter(): void
    {
        // Wat buiten de matrix valt: alles wat geen ISMS-scherm is. De
        // instellingenpagina's staan bewust buiten de blok-permissies
        // (implementatie/05 §4) en hebben hun eigen test hierboven; de
        // aanmeldschermen — inclusief de tweefactor-challenge (01d §7b) — horen
        // bij het inloggen en niet bij de rechtenmatrix.
        $buitenBeeld = [
            '/', 'up', 'login', 'forgot-password', 'confirm-password', 'settings',
            'tweefactor-challenge',
        ];

        $schermen = collect(Route::getRoutes())
            ->filter(fn (Routedefinitie $route) => in_array('GET', $route->methods(), true))
            ->map(fn (Routedefinitie $route) => $route->uri())
            ->reject(fn (string $uri) => str_contains($uri, '{')
                || str_starts_with($uri, 'flux/')
                || str_starts_with($uri, 'livewire-')
                || str_starts_with($uri, 'settings/')
                // Fortify's eigen account-endpoints (tweefactor aan/uit, de
                // QR-code, herstelcodes, wachtwoordbevestiging). Geen
                // ISMS-schermen maar onderdelen van het aanmeld- en
                // accountbeheer, net als `settings/` en de tweefactor-challenge
                // hierboven — ze staan in geen rechtenmatrix en hebben geen
                // blok-Gate.
                || str_starts_with($uri, 'user/')
                || in_array($uri, $buitenBeeld, true))
            ->unique()
            ->sort()
            ->map(fn (string $uri) => '/'.$uri)
            ->values()
            ->all();

        $this->assertSame($schermen, array_keys(self::SCHERMEN));
    }
}
