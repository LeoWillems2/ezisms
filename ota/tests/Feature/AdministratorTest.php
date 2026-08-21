<?php

namespace Tests\Feature;

use App\Livewire\ExportBeheer;
use App\Livewire\GebruikersOverzicht;
use App\Livewire\ToetsbestandenBeheer;
use App\Models\AuditLogregel;
use App\Models\Gebruiker;
use App\Models\Rol;
use App\Models\Taak;
use App\Models\Toetsopdracht;
use App\Support\ExterneBronnen;
use App\Support\Navigatie;
use App\Support\Rolregels;
use App\Support\ToetsBestanden;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * De rol die geen enkel ISMS-recht heeft (implementatie/01e §2).
 *
 * De kern van deze suite is niet wat de Administrator mág, maar wat hij niet
 * mag: één scherm, en verder een dichte deur op alles. Dat is met een lijstje
 * losse asserties niet vol te houden zodra er een blok bij komt, dus de
 * belangrijkste test loopt over `Navigatie` heen.
 */
class AdministratorTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->administrator = Gebruiker::factory()->metRol('Administrator')->create();

        Storage::fake(ToetsBestanden::DISK);
    }

    // --- Wat hij niet mag ---------------------------------------------------

    public function test_de_administrator_komt_op_geen_enkel_ismsscherm(): void
    {
        // De routes van elk menu-item dat de CISO ziet — dat is de volledige
        // ISMS-oppervlakte, en de lijst groeit vanzelf mee met nieuwe blokken.
        $this->actingAs(Gebruiker::factory()->metRol('CISO')->create());

        $routes = collect(Navigatie::zichtbareItems())
            ->pluck('route')
            ->reject(fn (string $route) => $route === 'beheer.toetsen');

        $this->assertGreaterThan(10, $routes->count());

        foreach ($routes as $route) {
            $this->actingAs($this->administrator)
                ->get(route($route))
                ->assertForbidden();
        }
    }

    public function test_de_administrator_ziet_alleen_zijn_eigen_menuitems(): void
    {
        $this->actingAs($this->administrator);

        $items = collect(Navigatie::zichtbareItems())->pluck('route');

        // Twee, en allebei uit het blok installatiebeheer. Komt hier ooit een
        // ISMS-route bij te staan, dan is dat het signaal dat er ergens een rij
        // in de rechtenmatrix is bijgekomen die er niet hoort.
        $this->assertSame(['beheer.toetsen', 'beheer.export'], $items->all());
    }

    public function test_de_administrator_landt_op_zijn_eigen_scherm(): void
    {
        // Het dashboard heeft bewust geen autorisatiecheck op blokniveau; elk
        // paneel checkt zijn eigen blok. Voor deze rol levert dat een leeg
        // scherm op, dus stuurt `/` hem naar wat hij wél heeft (01e §2.3).
        $this->actingAs($this->administrator)
            ->get('/')
            ->assertRedirect(route('beheer.toetsen'));
    }

    public function test_de_ismsrollen_landen_gewoon_op_het_dashboard(): void
    {
        foreach (['CISO', 'Medewerker', 'Auditor', 'Management'] as $rol) {
            $this->actingAs(Gebruiker::factory()->metRol($rol)->create())
                ->get('/')
                ->assertRedirect(route('dashboard'));
        }
    }

    public function test_de_ismsrollen_komen_niet_bij_het_beheerscherm(): void
    {
        foreach (['CISO', 'Medewerker', 'Auditor', 'Management'] as $rol) {
            $gebruiker = Gebruiker::factory()->metRol($rol)->create();

            $this->actingAs($gebruiker)->get(route('beheer.toetsen'))->assertForbidden();

            $this->actingAs($gebruiker);
            $this->assertNotContains(
                'beheer.toetsen',
                collect(Navigatie::zichtbareItems())->pluck('route')->all(),
            );
        }
    }

    public function test_de_administrator_ziet_geen_toetsresultaten(): void
    {
        // Persoonsgegevens van medewerkers, en ISMS-inhoud. Dat hij hier niet
        // bij kan volgt uit het ontbreken van een rij, niet uit een uitzondering.
        $this->actingAs($this->administrator)
            ->get(route('toetsen.resultaten'))
            ->assertForbidden();
    }

    // --- De onverenigbaarheid ----------------------------------------------

    public function test_een_ismsgebruiker_wordt_geen_administrator(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        $this->expectException(RuntimeException::class);

        $ciso->rolToewijzingen()->create([
            'rol_id' => Rol::where('naam', 'Administrator')->value('id'),
            'toegekend_op' => now(),
        ]);
    }

    public function test_een_administrator_krijgt_er_geen_ismsrol_bij(): void
    {
        $this->expectException(RuntimeException::class);

        $this->administrator->rolToewijzingen()->create([
            'rol_id' => Rol::where('naam', 'CISO')->value('id'),
            'toegekend_op' => now(),
        ]);
    }

    public function test_twee_ismsrollen_op_een_account_mogen_nog_steeds(): void
    {
        // De uitsluiting geldt alleen voor de Administrator. Voor de vier
        // ISMS-rollen blijft functiescheiding een organisatorische keuze die het
        // systeem faciliteert en niet afdwingt (01c §0).
        $gebruiker = Gebruiker::factory()->metRol('CISO')->create();

        $gebruiker->rolToewijzingen()->create([
            'rol_id' => Rol::where('naam', 'Management')->value('id'),
            'toegekend_op' => now(),
        ]);

        $this->assertCount(2, $gebruiker->refresh()->rollen);
    }

    public function test_de_regel_staat_op_een_plek(): void
    {
        $administratorRol = Rol::where('naam', Rolregels::EXCLUSIEF)->firstOrFail();
        $cisoRol = Rol::where('naam', 'CISO')->firstOrFail();

        $this->assertTrue(Rolregels::onverenigbaar($this->administrator, $cisoRol));
        $this->assertFalse(Rolregels::onverenigbaar($this->administrator, $administratorRol));
        $this->assertFalse(Rolregels::onverenigbaar(Gebruiker::factory()->create(), $administratorRol));
    }

    public function test_het_uitnodigingsscherm_waarschuwt_bij_de_exclusieve_rol(): void
    {
        $rollen = Rol::pluck('id', 'naam');

        Livewire::actingAs(Gebruiker::factory()->metRol('CISO')->create())
            ->test(GebruikersOverzicht::class)
            ->call('openUitnodigingsformulier')
            ->set('rolId', (string) $rollen['Administrator'])
            ->assertSee('geen toegang tot het ISMS')
            ->set('rolId', (string) $rollen['Medewerker'])
            ->assertDontSee('geen toegang tot het ISMS');
    }

    // --- Het beheerscherm ---------------------------------------------------

    public function test_de_administrator_plaatst_een_toetsbestand(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->set('bestand', UploadedFile::fake()->createWithContent(
                'nieuwe-toets.html',
                '<html><head><title>Nieuwe toets</title></head><body></body></html>',
            ))
            ->call('uploaden');

        Storage::disk(ToetsBestanden::DISK)->assertExists('nieuwe-toets.html');
        $this->assertSame('Nieuwe toets', ToetsBestanden::beschikbaar()['nieuwe-toets.html']);
    }

    public function test_een_toets_zonder_terugmelding_levert_een_waarschuwing_op(): void
    {
        // De stille faalwijze van 11-08-2026: de toets werkt, registreert niets,
        // en niemand ziet het tot iemand de taken naloopt.
        Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->set('bestand', UploadedFile::fake()->createWithContent(
                'stil.html', '<html><body>geen koppeling</body></html>',
            ))
            ->call('uploaden')
            ->assertSee('onQuizVoltooid');

        // Wel geplaatst: het is een waarschuwing, geen blokkade.
        Storage::disk(ToetsBestanden::DISK)->assertExists('stil.html');
    }

    public function test_een_toets_met_terugmelding_waarschuwt_niet(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->set('bestand', UploadedFile::fake()->createWithContent(
                'goed.html', '<html><script>function onQuizVoltooid(s,t,p){}</script></html>',
            ))
            ->call('uploaden')
            ->assertDontSee('meldt de uitslag waarschijnlijk niet terug');
    }

    public function test_een_toets_met_externe_bronnen_levert_een_waarschuwing_op(): void
    {
        // De CSP blokkeert ze al; deze melding zorgt dat de Administrator het
        // merkt vóór hij de toets uitzet, en niet via een klagende deelnemer.
        Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->set('bestand', UploadedFile::fake()->createWithContent(
                'extern.html',
                '<html><head>'
                .'<link rel="stylesheet" href="https://cdnjs.cloudflare.com/x.css">'
                .'<script src="https://cdn.tailwindcss.com"></script>'
                .'</head><body><script>function onQuizVoltooid(s,t,p){}</script></body></html>',
            ))
            ->call('uploaden')
            ->assertSee('cdn.tailwindcss.com')
            ->assertSee('cdnjs.cloudflare.com');

        // Wel geplaatst: het is een waarschuwing, geen blokkade.
        Storage::disk(ToetsBestanden::DISK)->assertExists('extern.html');
    }

    public function test_een_hyperlink_naar_buiten_is_geen_externe_bron(): void
    {
        // <a href> laadt niets — die volgt pas als iemand klikt. Een scan die
        // elke verwijzing aanstreept, wordt weggeklikt.
        Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->set('bestand', UploadedFile::fake()->createWithContent(
                'link.html',
                '<html><body><a href="https://www.ncsc.nl">meer lezen</a>'
                .'<script>function onQuizVoltooid(s,t,p){}</script></body></html>',
            ))
            ->call('uploaden')
            ->assertDontSee('worden bij het uitserveren geblokkeerd');
    }

    public function test_het_meegeleverde_skelet_haalt_zelf_niets_op(): void
    {
        // Het skelet is het vertrekpunt dat de bouwhulp uitdeelt. Zit hier ooit
        // een CDN in, dan verspreidt die zich over elke toets die ermee begint.
        $skelet = file_get_contents(resource_path('toetsen/skelet.html'));

        $this->assertSame([], ExterneBronnen::hosts($skelet));
        $this->assertStringContainsString('onQuizVoltooid(', $skelet);
    }

    public function test_de_helper_in_het_skelet_loopt_niet_uit_de_pas(): void
    {
        // Het skelet draagt een kopie van onQuizVoltooid.js, zodat het meteen
        // werkt. Deze test is de prijs daarvoor: wijzigt de helper, dan moet de
        // kopie mee.
        $skelet = file_get_contents(resource_path('toetsen/skelet.html'));
        $helper = trim(file_get_contents(resource_path('toetsen/onQuizVoltooid.js')));

        $this->assertStringContainsString($helper, $skelet);
    }

    public function test_alleen_html_komt_binnen(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->set('bestand', UploadedFile::fake()->createWithContent('script.js', 'alert(1)'))
            ->call('uploaden')
            ->assertHasErrors('bestand');

        Storage::disk(ToetsBestanden::DISK)->assertMissing('script.js');
    }

    public function test_een_te_groot_bestand_komt_niet_binnen(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->set('bestand', UploadedFile::fake()->create('groot.html', ToetsbestandenBeheer::MAX_KB + 1))
            ->call('uploaden')
            ->assertHasErrors('bestand');
    }

    public function test_een_bestandsnaam_met_een_pad_wordt_gesaneerd(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->set('bestand', UploadedFile::fake()->createWithContent(
                '../../evil.html', '<html></html>',
            ))
            ->call('uploaden');

        $bestanden = Storage::disk(ToetsBestanden::DISK)->allFiles();

        $this->assertSame(['evil.html'], $bestanden);
    }

    public function test_overschrijven_vraagt_een_bevestiging(): void
    {
        Storage::disk(ToetsBestanden::DISK)->put('bestaand.html', '<html>oud</html>');

        $component = Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->set('bestand', UploadedFile::fake()->createWithContent('bestaand.html', '<html>nieuw</html>'))
            ->call('uploaden');

        $this->assertSame('<html>oud</html>', Storage::disk(ToetsBestanden::DISK)->get('bestaand.html'));

        $component->call('uploaden');

        $this->assertSame('<html>nieuw</html>', Storage::disk(ToetsBestanden::DISK)->get('bestaand.html'));
    }

    // --- Export ---------------------------------------------------------------

    /** Een tijdelijke uitgang, zodat een testrun niet in /var/tmp schrijft. */
    private function exportmap(): string
    {
        $map = sys_get_temp_dir().'/isms-export-test-'.uniqid();
        config()->set('export.map', $map);

        return $map;
    }

    public function test_de_administrator_exporteert_na_bevestiging(): void
    {
        $map = $this->exportmap();

        try {
            $component = Livewire::actingAs($this->administrator)
                ->test(ExportBeheer::class)
                ->call('exporteer');

            // Eén klik exporteert niet: er staat een vraag tussen.
            $this->assertDirectoryDoesNotExist($map);
            $component->assertSee('Weet u het zeker?');

            $component->call('exporteer');

            $this->assertDirectoryExists($map);
            $this->assertNotEmpty(File::directories($map));
            // Het overzicht van 01-context-scope.md t/m 10-integraties.md.
            $this->assertNotEmpty(File::glob($map.'/*/00-overzicht.md'));
        } finally {
            File::deleteDirectory($map);
        }
    }

    public function test_de_export_komt_in_de_audit_trail(): void
    {
        $map = $this->exportmap();

        try {
            Livewire::actingAs($this->administrator)
                ->test(ExportBeheer::class)
                ->call('exporteer')
                ->call('exporteer');

            $regel = AuditLogregel::where('entiteit_type', 'isms_export')->firstOrFail();

            // Dát de inhoud het systeem verlaat en door wie: zonder deze regel is
            // een export een handeling zonder spoor.
            $this->assertSame('geexporteerd', $regel->actie);
            $this->assertSame($this->administrator->naam, $regel->gebruiker_naam);
            $this->assertSame('installatiebeheer', $regel->blok_naam);
            $this->assertFalse($regel->oude_waarde['met_persoonsgegevens']);
            $this->assertFalse($regel->oude_waarde['met_bewijs']);
        } finally {
            File::deleteDirectory($map);
        }
    }

    public function test_het_scherm_exporteert_zonder_persoonsgegevens_en_zonder_bewijs(): void
    {
        $this->exportmap();

        // Op de aanroep zelf en niet op de uitkomst: dát het scherm die twee
        // vlaggen niet zet, is de begrenzing van deze rol (01e §3). Een test op
        // de inhoud zou hier alleen bewijzen dat een lege demo geen namen bevat.
        Artisan::shouldReceive('call')
            ->once()
            ->withArgs(function (string $commando, array $parameters) {
                return $commando === 'isms:exporteer'
                    && array_keys($parameters) === ['--doel']
                    && ! array_key_exists('--met-persoonsgegevens', $parameters)
                    && ! array_key_exists('--met-bewijs', $parameters);
            })
            ->andReturn(0);

        Livewire::actingAs($this->administrator)
            ->test(ExportBeheer::class)
            ->call('exporteer')
            ->call('exporteer');
    }

    public function test_de_uitgang_wordt_aangemaakt_als_hij_ontbreekt(): void
    {
        $map = $this->exportmap();
        $this->assertDirectoryDoesNotExist($map);

        try {
            Livewire::actingAs($this->administrator)
                ->test(ExportBeheer::class)
                ->call('exporteer')
                ->call('exporteer');

            $this->assertDirectoryExists($map);
            // Hier komt de volledige inhoud van het ISMS te staan; niet voor
            // iedereen op de server leesbaar.
            $this->assertSame('0750', substr(sprintf('%o', fileperms($map)), -4));
        } finally {
            File::deleteDirectory($map);
        }
    }

    public function test_een_onbereikbare_uitgang_geeft_een_leesbare_melding(): void
    {
        // Een bestand als "ouder": mkdir kan daar niets onder maken. Dat bootst
        // na wat er echt gebeurde — /var/tmp was op de doelmachine niet
        // beschrijfbaar voor www-data — zonder rechten te hoeven verzetten.
        $bestand = tempnam(sys_get_temp_dir(), 'geen-map');
        config()->set('export.map', $bestand.'/isms_export');

        try {
            Livewire::actingAs($this->administrator)
                ->test(ExportBeheer::class)
                ->call('exporteer')
                ->call('exporteer')
                // Geen stack trace maar het commando dat het oplost.
                ->assertSee('De export is niet gemaakt')
                ->assertSee('sudo install -d -o www-data');

            $this->assertSame(0, AuditLogregel::where('entiteit_type', 'isms_export')->count());
        } finally {
            @unlink($bestand);
        }
    }

    public function test_de_ismsrollen_komen_niet_bij_het_exportscherm(): void
    {
        foreach (['CISO', 'Auditor', 'Management', 'Medewerker'] as $rol) {
            $this->actingAs(Gebruiker::factory()->metRol($rol)->create())
                ->get(route('beheer.export'))
                ->assertForbidden();
        }
    }

    // --- Verwijderen --------------------------------------------------------

    public function test_een_toets_met_een_openstaande_opdracht_blijft_staan(): void
    {
        Storage::disk(ToetsBestanden::DISK)->put('in-gebruik.html', '<html></html>');

        Toetsopdracht::factory()->create([
            'taak_id' => Taak::factory()->create()->id,
            'toets_bestand' => 'in-gebruik.html',
            'status' => 'uitgezet',
        ]);

        Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->call('verwijder', 'in-gebruik.html')
            ->call('verwijder', 'in-gebruik.html');

        Storage::disk(ToetsBestanden::DISK)->assertExists('in-gebruik.html');
    }

    public function test_een_gezakte_deelnemer_telt_ook_als_openstaand(): void
    {
        // Hij mag het opnieuw proberen, en dan moet het bestand er nog zijn.
        Storage::disk(ToetsBestanden::DISK)->put('herkansing.html', '<html></html>');

        Toetsopdracht::factory()->create([
            'taak_id' => Taak::factory()->create()->id,
            'toets_bestand' => 'herkansing.html',
            'status' => 'gezakt',
        ]);

        Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->call('verwijder', 'herkansing.html');

        Storage::disk(ToetsBestanden::DISK)->assertExists('herkansing.html');
    }

    public function test_verwijderen_lukt_na_bevestiging_als_niets_meer_openstaat(): void
    {
        Storage::disk(ToetsBestanden::DISK)->put('afgerond.html', '<html></html>');

        Toetsopdracht::factory()->create([
            'taak_id' => Taak::factory()->create()->id,
            'toets_bestand' => 'afgerond.html',
            'status' => 'geslaagd',
        ]);

        $component = Livewire::actingAs($this->administrator)
            ->test(ToetsbestandenBeheer::class)
            ->call('verwijder', 'afgerond.html');

        // Eerste klik vraagt om bevestiging.
        Storage::disk(ToetsBestanden::DISK)->assertExists('afgerond.html');

        $component->call('verwijder', 'afgerond.html');

        Storage::disk(ToetsBestanden::DISK)->assertMissing('afgerond.html');
    }
}
