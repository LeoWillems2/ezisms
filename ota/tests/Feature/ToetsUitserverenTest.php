<?php

namespace Tests\Feature;

use App\Models\Gebruiker;
use App\Models\Taak;
use App\Models\Toetsopdracht;
use App\Support\ToetsBestanden;
use App\Support\Toetsrespons;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * De toets komt niet meer uit de webmap maar door de applicatie heen
 * (implementatie/01e §1). Wat hier getoetst wordt is niet dát er HTML uitkomt,
 * maar de afscherming eromheen — dat is de hele reden van de verhuizing.
 */
class ToetsUitserverenTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = 'fixture-toets.html';

    private const HTML = '<!DOCTYPE html><html lang="nl"><head><title>Testtoets</title></head><body>hallo</body></html>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);

        Storage::fake(ToetsBestanden::DISK);
        Storage::disk(ToetsBestanden::DISK)->put(self::FIXTURE, self::HTML);
    }

    private function opdracht(string $bestand = self::FIXTURE): Toetsopdracht
    {
        return Toetsopdracht::factory()->create([
            'taak_id' => Taak::factory()->create()->id,
            'toets_bestand' => $bestand,
            'toets_titel' => 'Testtoets',
        ]);
    }

    // --- De deelnemerroute -------------------------------------------------

    public function test_de_toets_wordt_uitgeserveerd_op_de_token(): void
    {
        $opdracht = $this->opdracht();

        $this->get(route('toetsen.tonen', $opdracht->token))
            ->assertOk()
            ->assertSee('hallo', escape: false);
    }

    /**
     * De vier vlaggen zijn geen smaakkwestie: drie ervan staan er omdat
     * onQuizVoltooid ze nodig heeft, en `allow-same-origin` mag er nooit bij
     * (01e §1.4). Deze test is de rem op "even een vlag erbij".
     */
    public function test_de_toets_draait_in_een_sandbox_zonder_eigen_origin(): void
    {
        $respons = $this->get(route('toetsen.tonen', $this->opdracht()->token));

        $csp = $respons->headers->get('Content-Security-Policy');

        $this->assertSame(Toetsrespons::SANDBOX, $csp);
        $this->assertStringStartsWith('sandbox', $csp);
        $this->assertStringNotContainsString('allow-same-origin', $csp);

        foreach (['allow-scripts', 'allow-forms', 'allow-modals', 'allow-top-navigation'] as $vlag) {
            $this->assertStringContainsString($vlag, $csp);
        }

        $this->assertSame('nosniff', $respons->headers->get('X-Content-Type-Options'));
    }

    public function test_een_onbekende_token_geeft_404(): void
    {
        $this->get(route('toetsen.tonen', 'bestaat-niet'))->assertNotFound();
    }

    public function test_een_opdracht_zonder_bestand_geeft_404_en_noemt_de_toets(): void
    {
        $opdracht = $this->opdracht('weggegooid.html');

        $this->get(route('toetsen.tonen', $opdracht->token))->assertNotFound();
        $this->assertSame('Testtoets', $opdracht->toets_titel);
    }

    public function test_de_deelnemerlink_draagt_de_token_in_het_pad_en_in_de_querystring(): void
    {
        $opdracht = $this->opdracht();
        $url = $opdracht->deelnemerUrl();

        // In het pad omdat de route hem daar leest; in ?callback= omdat élk
        // bestaand toetsbestand onQuizVoltooid meedraagt en die hem daar zoekt.
        // Zonder dat tweede registreert een al uitgeleverde toets niets meer.
        $this->assertStringContainsString('/toetsen/tonen/'.$opdracht->token, $url);
        $this->assertStringContainsString('callback='.$opdracht->token, $url);
    }

    // --- De voorbeeldroute -------------------------------------------------

    public function test_wie_toetsen_uitzet_mag_een_voorbeeld_bekijken(): void
    {
        $this->actingAs(Gebruiker::factory()->metRol('CISO')->create())
            ->get(route('toetsen.voorbeeld', self::FIXTURE))
            ->assertOk()
            ->assertHeader('Content-Security-Policy', Toetsrespons::SANDBOX);
    }

    public function test_een_medewerker_komt_niet_bij_de_voorbeeldroute(): void
    {
        $this->actingAs(Gebruiker::factory()->metRol('Medewerker')->create())
            ->get(route('toetsen.voorbeeld', self::FIXTURE))
            ->assertForbidden();
    }

    public function test_de_voorbeeldroute_weigert_een_pad(): void
    {
        // Het bestand bestaat, maar niet onder deze naam. Een verzoek met een
        // pad erin is geen vergissing.
        $this->actingAs(Gebruiker::factory()->metRol('CISO')->create())
            ->get('/toetsen/voorbeeld/'.urlencode('../'.self::FIXTURE))
            ->assertNotFound();
    }

    // --- CORS op het terugmeldkanaal ---------------------------------------

    /**
     * Een sandbox zonder `allow-same-origin` levert een opake origin op, en die
     * stuurt `Origin: null`. Zonder deze koppen faalt het terugmelden van elke
     * uitslag — pas nadat de deelnemer de toets gemaakt heeft (01e §1.5).
     */
    public function test_het_terugmeldkanaal_staat_open_voor_een_opake_origin(): void
    {
        $opdracht = $this->opdracht();

        $this->call('OPTIONS', route('toetsen.callback', $opdracht->token), [], [], [], [
            'HTTP_ORIGIN' => 'null',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', '*');

        $this->postJson(route('toetsen.callback', $opdracht->token), [
            'score' => 8, 'total' => 10, 'passed' => true,
        ], ['Origin' => 'null'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_cors_geldt_alleen_voor_het_terugmeldkanaal(): void
    {
        $this->get(route('toetsen.tonen', $this->opdracht()->token), ['Origin' => 'https://elders.example'])
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    // --- De verhuizing ------------------------------------------------------

    /**
     * De migratie die bestaande installaties meeneemt (01e §1.6). Eigen fixture
     * in `public/toetsen`, want wat daar op deze machine toevallig staat is geen
     * grond voor een test.
     */
    public function test_de_migratie_kopieert_bestaande_toetsen_en_is_idempotent(): void
    {
        $map = public_path('toetsen');
        $oud = $map.'/migratie-fixture.html';

        if (! is_dir($map)) {
            mkdir($map, 0755, true);
        }
        file_put_contents($oud, '<html><head><title>Oude toets</title></head><body></body></html>');

        try {
            $migratie = require database_path('migrations/0001_01_01_000050_toetsen_naar_de_private_schijf.php');

            $migratie->up();
            Storage::disk(ToetsBestanden::DISK)->assertExists('migratie-fixture.html');

            // Iemand werkt de toets bij ná de verhuizing; een tweede uitrol mag
            // die wijziging niet terugdraaien.
            Storage::disk(ToetsBestanden::DISK)->put('migratie-fixture.html', '<html>bijgewerkt</html>');
            $migratie->up();

            $this->assertSame(
                '<html>bijgewerkt</html>',
                Storage::disk(ToetsBestanden::DISK)->get('migratie-fixture.html'),
            );

            // Kopiëren, niet verplaatsen: bij een terugrol werkt de vorige
            // release nog.
            $this->assertFileExists($oud);
        } finally {
            @unlink($oud);
        }
    }

    // --- De helper die de toetsmaker meekrijgt ------------------------------

    /**
     * `onQuizVoltooid.js` reist als tekst mee naar een toets en wordt daar
     * ingeplakt; er is dus niets dat hem met de applicatie gelijk houdt. Twee
     * dingen mogen daarom niet stilletjes uit elkaar lopen.
     */
    public function test_de_meegeleverde_helper_past_bij_de_callbackroute(): void
    {
        $helper = file_get_contents(resource_path('toetsen/onQuizVoltooid.js'));

        // 1. Het pad. Wordt de route hernoemd, dan post elke al uitgeleverde
        //    toets naar een 404 — zonder dat iemand het merkt.
        $pad = parse_url(route('toetsen.callback', 'TOKEN'), PHP_URL_PATH);
        $this->assertStringContainsString(
            str_replace('TOKEN', '', $pad),
            $helper,
        );

        // 2. De aanroep. De helper definiëren zonder hem aan te roepen is de
        //    faalwijze van 11-08-2026: alles werkt, er komt niets binnen. Het
        //    voorbeeld hoort er dus in te staan, mét de zak-variant.
        $this->assertStringContainsString('onQuizVoltooid(score, total, true)', $helper);
        $this->assertStringContainsString('onQuizVoltooid(score, total, false)', $helper);
    }

    // --- De lijst ----------------------------------------------------------

    public function test_de_beschikbare_toetsen_komen_van_de_disk(): void
    {
        Storage::disk(ToetsBestanden::DISK)->put('geen-titel.html', '<html><body></body></html>');
        Storage::disk(ToetsBestanden::DISK)->put('leesmij.txt', 'geen toets');

        $beschikbaar = ToetsBestanden::beschikbaar();

        $this->assertSame('Testtoets', $beschikbaar[self::FIXTURE]);
        // Zonder <title> valt de naam terug op het bestand zelf.
        $this->assertSame('geen-titel.html', $beschikbaar['geen-titel.html']);
        // Alleen HTML telt mee als toets.
        $this->assertArrayNotHasKey('leesmij.txt', $beschikbaar);
    }
}
