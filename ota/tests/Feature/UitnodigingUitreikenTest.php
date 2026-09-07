<?php

namespace Tests\Feature;

use App\Livewire\GebruikersOverzicht;
use App\Mail\GebruikerUitgenodigd;
use App\Models\AuditLogregel;
use App\Models\Gebruiker;
use App\Models\Rol;
use App\Support\Uitnodigingsbrief;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Een uitnodiging uitreiken op een installatie zonder mailkanaal
 * (implementatie/01i).
 */
class UitnodigingUitreikenTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    /**
     * De testomgeving draait op het `array`-transport, dat als werkend kanaal
     * telt (01i §0). Een installatie in een afgeschermd netwerk staat op `log`.
     */
    private function zonderMailkanaal(): void
    {
        config(['mail.default' => 'log']);
    }

    private function nodigUit(): Gebruiker
    {
        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->set([
                'naam' => 'Jan Jansen',
                'email' => 'jan@example.com',
                'rolId' => Rol::where('naam', 'Medewerker')->value('id'),
            ])
            ->call('uitnodigen')
            ->assertHasNoErrors();

        return Gebruiker::where('email', 'jan@example.com')->firstOrFail();
    }

    public function test_zonder_mailkanaal_wordt_er_niets_verstuurd_maar_opent_de_modal(): void
    {
        Mail::fake();
        $this->zonderMailkanaal();

        $component = Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->set([
                'naam' => 'Jan Jansen',
                'email' => 'jan@example.com',
                'rolId' => Rol::where('naam', 'Medewerker')->value('id'),
            ])
            ->call('uitnodigen')
            ->assertHasNoErrors();

        $gebruiker = Gebruiker::where('email', 'jan@example.com')->firstOrFail();

        Mail::assertNothingSent();
        $component->assertSet('toontHandmatigeUitnodiging', true)
            ->assertSet('handmatigeUitnodigingId', $gebruiker->id)
            // De modal vertelt wat er niet gebeurd is, en waarom.
            ->assertSee('geen uitnodiging verstuurd')
            ->assertSee('MAIL_MAILER');

        // Er is nog niets uitgereikt: dat gebeurt pas bij de download (§0).
        $this->assertNull($gebruiker->uitnodiging_verstuurd_op);
        $this->assertNull($gebruiker->uitnodiging_kanaal);
    }

    public function test_met_mailkanaal_blijft_de_mailweg_en_wordt_het_kanaal_vastgelegd(): void
    {
        Mail::fake();

        $gebruiker = $this->nodigUit();

        Mail::assertSent(GebruikerUitgenodigd::class);
        $this->assertSame('mail', $gebruiker->uitnodiging_kanaal);
        $this->assertNotNull($gebruiker->uitnodiging_verstuurd_op);
    }

    public function test_de_download_levert_een_tekstbestand_met_een_werkende_link(): void
    {
        Mail::fake();
        $this->zonderMailkanaal();
        $gebruiker = $this->nodigUit();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->set('handmatigeUitnodigingId', $gebruiker->id)
            ->call('downloadUitnodiging')
            ->assertFileDownloaded('uitnodiging-jan-jansen-'.now()->format('Ymd').'.txt')
            ->assertSet('toontHandmatigeUitnodiging', false);

        // De inhoud van de brief los toetsen: de download zelf is een
        // base64-blok in de Livewire-respons, en de tekst is deterministisch.
        $this->actingAs($this->ciso);
        $tekst = Uitnodigingsbrief::voor($gebruiker->fresh())->tekst();

        $this->assertStringContainsString('Jan Jansen', $tekst);
        $this->assertStringContainsString('jan@example.com', $tekst);
        $this->assertStringContainsString($this->ciso->naam, $tekst);

        // De link in de brief moet het uitnodigingsformulier openen; anders is
        // het bestand een dood briefje.
        preg_match('~https?://\S+~', $tekst, $treffer);
        $this->assertNotEmpty($treffer, 'De brief bevat geen link.');
        $this->get($treffer[0])->assertOk()->assertSee('jan@example.com');
    }

    public function test_de_download_legt_de_uitreiking_vast(): void
    {
        Mail::fake();
        $this->zonderMailkanaal();
        $gebruiker = $this->nodigUit();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->set('handmatigeUitnodigingId', $gebruiker->id)
            ->call('downloadUitnodiging');

        $gebruiker->refresh();
        $this->assertSame('bestand', $gebruiker->uitnodiging_kanaal);
        $this->assertNotNull($gebruiker->uitnodiging_verstuurd_op);
        $this->assertSame('Handmatig uitgereikt op', $gebruiker->uitreikingLabel());

        $this->assertTrue(
            AuditLogregel::where('entiteit_id', $gebruiker->id)
                ->where('nieuwe_waarde->uitnodiging', 'als bestand uitgereikt')
                ->exists(),
            'De uitreiking staat niet in de audit trail.'
        );
    }

    public function test_download_weigert_een_account_dat_inmiddels_in_gebruik_is(): void
    {
        Mail::fake();
        $this->zonderMailkanaal();
        $gebruiker = $this->nodigUit();

        // Tussen het openen van de modal en de klik heeft de betrokkene de
        // uitnodiging geaccepteerd.
        $gebruiker->update(['status' => 'actief']);

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->set('handmatigeUitnodigingId', $gebruiker->id)
            ->call('downloadUitnodiging')
            ->assertNoFileDownloaded()
            ->assertSet('toontHandmatigeUitnodiging', false);

        $this->assertNull($gebruiker->fresh()->uitnodiging_kanaal);
    }

    public function test_auditor_mag_geen_uitnodigingsbestand_ophalen(): void
    {
        Mail::fake();
        $this->zonderMailkanaal();
        $gebruiker = $this->nodigUit();
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        Livewire::actingAs($auditor)
            ->test(GebruikersOverzicht::class)
            ->set('handmatigeUitnodigingId', $gebruiker->id)
            ->call('downloadUitnodiging')
            ->assertForbidden();

        $this->assertNull($gebruiker->fresh()->uitnodiging_kanaal);
    }

    public function test_een_account_zonder_uitgereikte_uitnodiging_vraagt_erom(): void
    {
        // De badge zegt "Uitgenodigd" zodra het account bestaat. Is er niets de
        // deur uit gegaan — modal gesloten zonder download, of een mail die
        // faalde — dan is dat precies wat er niet gebeurd is (01i §7).
        Gebruiker::factory()->uitgenodigd()->create([
            'naam' => 'Nooit Uitgereikt',
            'uitnodiging_verstuurd_op' => null,
        ]);

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->assertSee('Nog uitnodigen');
    }

    public function test_een_uitgereikte_uitnodiging_vraagt_er_niet_om(): void
    {
        Gebruiker::factory()->uitgenodigd()->create([
            'uitnodiging_verstuurd_op' => now(),
            'uitnodiging_kanaal' => 'bestand',
        ]);

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->assertSee('Handmatig uitgereikt op')
            ->assertDontSee('Nog uitnodigen');
    }

    public function test_opnieuw_uitreiken_zonder_mailkanaal_opent_de_modal(): void
    {
        Mail::fake();
        $gebruiker = Gebruiker::factory()->uitgenodigd()->create();
        $this->zonderMailkanaal();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('uitnodigingOpnieuwVersturen', $gebruiker->id)
            ->assertSet('toontHandmatigeUitnodiging', true)
            ->assertSet('handmatigeUitnodigingId', $gebruiker->id);

        Mail::assertNothingSent();
    }
}
