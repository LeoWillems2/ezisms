<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLogregel;
use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Gebruiker;
use App\Models\Leverancier;
use App\Models\OrganisatieEenheid;
use App\Models\Risico;
use App\Models\ScopeVerklaring;
use App\Models\Systeem;
use App\Models\Trainingsmodule;
use App\Support\ToetsBestanden;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * De simulatiemotor op een verkorte tijdlijn (M0–M2).
 *
 * De volledige tijdlijn staat in `DemoEindstandTest`; die is traag en toetst de
 * eindstand. Hier gaat het om het mechanisme: handelt de motor namens de juiste
 * persoon, valt hij om bij een fixture die de verkeerde aanwijst, en laat hij de
 * klok netjes achter.
 */
class DemoVulTest extends TestCase
{
    use RefreshDatabase;

    private string $map;

    protected function setUp(): void
    {
        parent::setUp();

        // Het commando weigert buiten local/demo, en in zorgmodus; beide
        // weigeringen toetst een eigen test hieronder, de rest van dit bestand
        // heeft een omgeving én een profiel nodig waarin de motor mág draaien.
        $this->app['env'] = 'local';
        config()->set('norm.actief', 'iso27001');

        Storage::fake('bewijs');
        // Ook de toetsen-disk: de demo zet er een toetsbestand op, en dat hoort
        // niet in de echte storage/ van wie de suite draait terecht te komen.
        Storage::fake(ToetsBestanden::DISK);

        $this->map = $this->fixturesTot(2);
    }

    protected function tearDown(): void
    {
        $this->ruimOp($this->map);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_weigert_buiten_local_en_demo(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('isms:demo-vul', ['--fixtures' => $this->map])
            ->expectsOutputToContain('draait alleen in local of demo')
            ->assertFailed();

        $this->assertSame(0, Gebruiker::count());
    }

    public function test_vult_de_verkorte_tijdlijn(): void
    {
        $this->vul();

        // De acht accounts uit personen.json minus Aurelius, die pas in M3 komt.
        $this->assertSame(7, Gebruiker::count());
        $this->assertSame(10, OrganisatieEenheid::count());

        $scope = ScopeVerklaring::where('status', 'actief')->firstOrFail();
        $this->assertSame(2, $scope->uitsluitingen()->count());

        $this->assertSame(5, Systeem::count());
        $this->assertSame(8, Asset::count());
        // Ronde 1 van het risicoregister; 11 t/m 16 komen pas vanaf M3.
        $this->assertSame(10, Risico::count());
        $this->assertSame(10, Risico::whereNotNull('risicoscore')->count());
        $this->assertSame(4, Leverancier::count());

        // Vier documenten gepubliceerd in M1 en M2; alle vier actief, elk met
        // precies één actieve versie.
        $this->assertSame(4, Beleidsdocument::where('status', 'actief')->count());
        $this->assertSame(4, Beleidsversie::where('status', 'actief')->count());
    }

    public function test_de_module_met_een_toets_krijgt_zijn_bestand_op_de_disk(): void
    {
        // De tijdlijn tot M10: dan bestaat de beheerderstraining, en die draagt
        // als enige een toetsbestand. In een echte installatie zet de
        // Administrator dat bestand neer; een demovulling kan dat niet
        // nabootsen, dus brengt ze het zelf mee.
        $map = $this->fixturesTot(10);

        try {
            $this->artisan('isms:demo-vul', ['--fixtures' => $map, '--stil' => true])->assertSuccessful();

            $module = Trainingsmodule::where('titel', 'Veilig beheer van de productieomgeving')->firstOrFail();
            $this->assertSame('owasp1.html', $module->toets_bestand);
            Storage::disk(ToetsBestanden::DISK)->assertExists('owasp1.html');
        } finally {
            $this->ruimOp($map);
        }
    }

    public function test_audit_trail_staat_op_naam_van_de_handelende_gebruiker(): void
    {
        $this->vul();

        $scope = ScopeVerklaring::where('status', 'actief')->firstOrFail();

        $activering = AuditLogregel::where('entiteit_type', $scope->getMorphClass())
            ->where('entiteit_id', $scope->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($activering, 'De scope-activering staat niet in de audit trail.');
        $this->assertSame('Bobo Spruitje', $activering->gebruiker_naam);
        $this->assertNotSame('Systeem (geplande taak)', $activering->gebruiker_naam);
    }

    public function test_de_directiehandelingen_zijn_door_een_management_account_gedaan(): void
    {
        $this->vul();

        $scope = ScopeVerklaring::where('status', 'actief')->firstOrFail();
        $this->assertSame('Bobo Spruitje', $scope->goedgekeurd_door);

        // Publiceren is sinds implementatie/01c een goedkeuractie: elke actieve
        // versie is door een directeur vastgesteld, niet door de CISO.
        $goedkeurders = Beleidsversie::where('status', 'actief')
            ->with('goedkeurder.rollen')
            ->get()
            ->pluck('goedkeurder');

        $this->assertCount(4, $goedkeurders);

        foreach ($goedkeurders as $goedkeurder) {
            $this->assertContains(
                'Management',
                $goedkeurder->rollen->pluck('naam')->all(),
                "{$goedkeurder->naam} stelde beleid vast maar heeft de rol Management niet.",
            );
        }
    }

    public function test_een_fixture_die_de_verkeerde_persoon_aanwijst_faalt(): void
    {
        // De CISO stelt de scope niet vast — dat is precies de functiescheiding
        // die de motor als assertie meeneemt.
        $map = $this->fixturesTot(1, function (array $tijdlijn) {
            $tijdlijn['maanden'][1]['gebeurtenissen'][0]['door'] = 'ciske';

            return $tijdlijn;
        });

        $this->artisan('isms:demo-vul', ['--fixtures' => $map])
            ->expectsOutputToContain('Vullen afgebroken')
            ->assertFailed();

        // Niet alleen de melding telt: de handeling mag ook niet zijn uitgevoerd.
        // De verklaring blijft concept, want de CISO stelt hem niet vast.
        $this->assertSame(0, ScopeVerklaring::where('status', 'actief')->count());
        $this->assertSame(1, ScopeVerklaring::where('status', 'concept')->count());

        $this->ruimOp($map);
    }

    public function test_een_onbekend_gebeurtenistype_faalt_met_een_leesbare_melding(): void
    {
        $map = $this->fixturesTot(0, function (array $tijdlijn) {
            $tijdlijn['maanden'][0]['gebeurtenissen'][] = ['type' => 'kaas_snijden'];

            return $tijdlijn;
        });

        $this->artisan('isms:demo-vul', ['--fixtures' => $map])
            ->expectsOutputToContain("geen handler voor gebeurtenistype 'kaas_snijden'")
            ->assertFailed();

        $this->ruimOp($map);
    }

    public function test_de_klok_wordt_hersteld_ook_als_het_vullen_faalt(): void
    {
        $map = $this->fixturesTot(0, function (array $tijdlijn) {
            $tijdlijn['maanden'][0]['gebeurtenissen'][] = ['type' => 'kaas_snijden'];

            return $tijdlijn;
        });

        $this->artisan('isms:demo-vul', ['--fixtures' => $map])->assertFailed();

        // Een proces dat met een verzette klok eindigt, schrijft de rest van zijn
        // werk in het verleden — vandaar de `finally` in de motor.
        $this->assertFalse(Carbon::hasTestNow(), 'De klok staat na een mislukte vulling nog vast.');

        $this->ruimOp($map);
    }

    public function test_een_ontbrekend_fixtures_bestand_wordt_gemeld(): void
    {
        $map = $this->fixturesTot(0);
        unlink($map.'/reviews.json');

        $this->artisan('isms:demo-vul', ['--fixtures' => $map])
            ->expectsOutputToContain('reviews.json ontbreekt')
            ->assertFailed();

        $this->ruimOp($map);
    }

    /**
     * De simulatiemotor is ISO-only (nen7510-opzet.md §4.8), en dat moet een
     * expliciete weigering zijn en geen stilzwijgende aanname. Zonder deze
     * controle draait de demo gewoon door op een zorginstallatie en levert ze een
     * compleet ogend ISMS op met 101 in plaats van 93 SoA-regels, acht daarvan
     * onbeoordeeld en overal een lege maatregelomschrijving. Gemeten: met
     * `ISMS_NORM=nen7510` was dit de enige inhoudelijke fout in de hele suite.
     */
    #[Group('nen7510')]
    public function test_de_demo_weigert_op_een_zorginstallatie(): void
    {
        config()->set('norm.actief', 'nen7510');

        $this->artisan('isms:demo-vul', ['--fixtures' => $this->map])
            ->expectsOutputToContain('draait niet op een NEN 7510-installatie')
            ->assertFailed();

        // Niets aangeraakt: de weigering komt vóór het legen van de database.
        $this->assertSame(0, Gebruiker::count());
    }

    /**
     * Dezelfde weigering onder de BIO, en dit is precies het geval waarvoor de
     * controle op `is('iso27001')` en niet op een capaciteit staat: de BIO deelt
     * de 93 beheersmaatregelen met ISO, dus aan de controlset is dit profiel niet
     * te herkennen. Wat het scenario zou laten liggen zijn de 118
     * overheidsmaatregelen eronder — en inhoudelijk is FruitBV een fruithandel
     * en geen gemeente (00q §10).
     */
    #[Group('bio2')]
    public function test_de_demo_weigert_op_een_bio_installatie(): void
    {
        config()->set('norm.actief', 'bio2');

        $this->artisan('isms:demo-vul', ['--fixtures' => $this->map])
            ->expectsOutputToContain('draait niet op een BIO2-installatie')
            ->assertFailed();

        $this->assertSame(0, Gebruiker::count());
    }

    public function test_een_tweede_vulling_wordt_geweigerd_zolang_de_vergrendeling_staat(): void
    {
        Cache::lock('isms:demo-vul', 3600)->get();

        $this->artisan('isms:demo-vul', ['--fixtures' => $this->map])
            ->expectsOutputToContain('Er draait al een isms:demo-vul')
            ->assertFailed();

        // Met --ontgrendel komt hij er wél doorheen.
        $this->artisan('isms:demo-vul', ['--fixtures' => $this->map, '--ontgrendel' => true, '--stil' => true])
            ->assertSuccessful();
    }

    /**
     * De gegenereerde wachtwoorden gaan naar een bestand en niet naar het
     * scherm. `deploy.sh` draait dit commando onbeheerd mee in een uitrol, en
     * alles wat het afdrukt belandt dan in het uitrollog dat bewaard blijft in
     * `shared/installatie/`. Werkende inloggegevens horen daar niet in.
     */
    public function test_de_wachtwoorden_komen_in_een_bestand_en_niet_op_het_scherm(): void
    {
        Storage::fake('local');

        $uitvoer = $this->artisanUitvoer();

        $this->assertTrue(Storage::disk('local')->exists('demo-inloggegevens.txt'));

        $inhoud = Storage::disk('local')->get('demo-inloggegevens.txt');
        $this->assertStringContainsString('ciso@acme.example', $inhoud);

        // Elk wachtwoord uit het bestand moet ontbreken in wat er is afgedrukt.
        foreach (explode("\n", trim($inhoud)) as $regel) {
            if (! str_contains($regel, "\t")) {
                continue;
            }
            [$email, $wachtwoord] = explode("\t", $regel, 2);
            $this->assertStringNotContainsString($wachtwoord, $uitvoer, "wachtwoord van {$email} staat in de uitvoer");
        }

        // Het pad wel, anders weet niemand waar hij moet kijken.
        $this->assertStringContainsString('demo-inloggegevens.txt', $uitvoer);
    }

    // --- Hulpjes -----------------------------------------------------------

    /**
     * Vult de demo en geeft terug wat het commando heeft afgedrukt.
     *
     * Met een eigen buffer, want `Artisan::output()` helpt hier niet: de motor
     * roept zelf `isms:meet-kpis` en consorten aan, en dan houdt die functie de
     * uitvoer van de láátste aanroep over. `$this->artisan()` valt ook af — die
     * wil zijn verwachtingen vooraf, en de wachtwoorden zijn pas ná de vulling
     * bekend.
     */
    private function artisanUitvoer(): string
    {
        $this->withoutMockingConsoleOutput();

        $buffer = new BufferedOutput;
        $code = $this->app[Kernel::class]->call(
            'isms:demo-vul',
            ['--fixtures' => $this->map, '--stil' => true],
            $buffer,
        );
        $this->assertSame(0, $code);

        return $buffer->fetch();
    }

    private function vul(): void
    {
        $this->artisan('isms:demo-vul', ['--fixtures' => $this->map, '--stil' => true])
            ->assertSuccessful();
    }

    /**
     * Een kopie van de echte fixtures met een tijdlijn tot en met de opgegeven
     * maand. Bewust de echte bestanden en geen eigen minivariant: de fixtures
     * zijn wat de motor moet aankunnen, en een test op nagemaakte fixtures toont
     * niets over de echte.
     */
    private function fixturesTot(int $tot, ?callable $pas = null): string
    {
        $bron = base_path('../saasdemo/data');
        $doel = sys_get_temp_dir().'/demo-fixtures-'.uniqid();
        mkdir($doel);

        foreach (glob($bron.'/*.json') as $bestand) {
            copy($bestand, $doel.'/'.basename($bestand));
        }

        // De bijlagen horen erbij: een trainingsmodule met een toets zoekt zijn
        // bestand naast de JSON. Zonder deze kopie faalt elke afgeknotte
        // fixtureset zodra de tijdlijn ver genoeg loopt om die module te maken.
        if (is_dir($bron.'/toetsen')) {
            mkdir($doel.'/toetsen');
            foreach (glob($bron.'/toetsen/*') as $bestand) {
                copy($bestand, $doel.'/toetsen/'.basename($bestand));
            }
        }

        $tijdlijn = json_decode(file_get_contents($bron.'/tijdlijn.json'), true);
        $tijdlijn['maanden'] = array_values(array_filter(
            $tijdlijn['maanden'],
            fn (array $maand) => $maand['maand'] <= $tot,
        ));

        if ($pas !== null) {
            $tijdlijn = $pas($tijdlijn);
        }

        file_put_contents($doel.'/tijdlijn.json', json_encode($tijdlijn, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $doel;
    }

    private function ruimOp(string $map): void
    {
        if (! is_dir($map)) {
            return;
        }

        array_map('unlink', glob($map.'/*.json') ?: []);

        if (is_dir($map.'/toetsen')) {
            array_map('unlink', glob($map.'/toetsen/*') ?: []);
            rmdir($map.'/toetsen');
        }

        rmdir($map);
    }
}
