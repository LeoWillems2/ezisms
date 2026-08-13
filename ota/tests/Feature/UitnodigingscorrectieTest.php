<?php

namespace Tests\Feature;

use App\Livewire\GebruikersOverzicht;
use App\Mail\GebruikerUitgenodigd;
use App\Models\AuditLogregel;
use App\Models\Gebruiker;
use App\Models\Rol;
use App\Support\Uitnodiging;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Een verkeerd e-mailadres bij een uitnodiging (implementatie/01g).
 *
 * De test die het hele plan draagt is
 * {@see self::test_de_oude_uitnodigingslink_is_na_een_correctie_dood()}. Zonder
 * die eigenschap is de correctie erger dan niets: dan houdt de ontvanger van het
 * foute adres zijn werkende link, en stuurt de correctie er een tweede naar het
 * juiste adres — twee mensen met toegang tot hetzelfde account, en niemand die
 * het ziet.
 */
class UitnodigingscorrectieTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    private function uitgenodigde(string $email = 'tyfout@fruitbv.nl'): Gebruiker
    {
        return Gebruiker::factory()->uitgenodigd()->create([
            'naam' => 'Jan Jansen',
            'email' => $email,
            'uitnodiging_verstuurd_op' => now(),
        ]);
    }

    /** @return Testable */
    private function corrigeer(Gebruiker $gebruiker, string $naam, string $email)
    {
        return Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openCorrectieformulier', $gebruiker->id)
            ->set('correctieNaam', $naam)
            ->set('correctieEmail', $email)
            ->call('corrigeren');
    }

    // ── De kern ──────────────────────────────────────────────────────────────

    public function test_de_ciso_corrigeert_naam_en_adres_en_er_gaat_een_nieuwe_uitnodiging_uit(): void
    {
        Mail::fake();
        $gebruiker = $this->uitgenodigde();

        $this->corrigeer($gebruiker, 'Jan Jansen', 'jan.jansen@fruitbv.nl')->assertHasNoErrors();

        $gebruiker->refresh();
        $this->assertSame('jan.jansen@fruitbv.nl', $gebruiker->email);
        $this->assertSame('uitgenodigd', $gebruiker->status);

        Mail::assertSent(
            GebruikerUitgenodigd::class,
            fn (GebruikerUitgenodigd $mail) => $mail->hasTo('jan.jansen@fruitbv.nl'),
        );
    }

    public function test_de_oude_uitnodigingslink_is_na_een_correctie_dood(): void
    {
        Mail::fake();
        $gebruiker = $this->uitgenodigde();

        // De link zoals de ontvanger van het foute adres hem in zijn mailbox heeft.
        $oudeLink = Uitnodiging::link($gebruiker);
        $this->get($oudeLink)->assertOk();

        $this->corrigeer($gebruiker, 'Jan Jansen', 'jan.jansen@fruitbv.nl');

        // Het token is afgeleid van de wachtwoord-hash, en die is mee-geroteerd.
        $this->get($oudeLink)->assertForbidden();
    }

    public function test_de_link_uit_de_nieuwe_uitnodiging_werkt_wel(): void
    {
        Mail::fake();
        $gebruiker = $this->uitgenodigde();

        $this->corrigeer($gebruiker, 'Jan Jansen', 'jan.jansen@fruitbv.nl');

        // De mailable bouwt zijn link bij het renderen, uit het model dat hij
        // meekreeg; dit is dus de link die de ontvanger krijgt.
        $verstuurd = null;
        Mail::assertSent(GebruikerUitgenodigd::class, function (GebruikerUitgenodigd $mail) use (&$verstuurd) {
            $verstuurd = Uitnodiging::link($mail->gebruiker);

            return true;
        });

        $this->get($verstuurd)->assertOk();
    }

    // ── Waar de knop niet mag werken ─────────────────────────────────────────

    /**
     * De gevaarlijke situatie — een vreemde die de uitnodiging accepteerde —
     * hoort deze knop niet te kunnen bereiken. Het scherm toont hem daar niet,
     * en de component weigert ook als iemand hem alsnog met een geldig id
     * aanroept (01g §0).
     */
    public function test_een_account_dat_niet_meer_uitgenodigd_staat_wordt_niet_gecorrigeerd(): void
    {
        Mail::fake();

        foreach (['actief', 'geblokkeerd', 'gedeactiveerd'] as $status) {
            $gebruiker = Gebruiker::factory()->create([
                'status' => $status,
                'email' => "houder-{$status}@fruitbv.nl",
            ]);

            // Bewust het id rechtstreeks zetten in plaats van via
            // `openCorrectieformulier()`: die weigert de modal al te openen, en
            // dan zou deze test de tweede controle — die in `corrigeren()` zelf
            // — niet raken. Dit is het pad van iemand die met het id knoeit.
            Livewire::actingAs($this->ciso)
                ->test(GebruikersOverzicht::class)
                ->set('correctieGebruikerId', $gebruiker->id)
                ->set('correctieNaam', 'Andere Naam')
                ->set('correctieEmail', "nieuw-{$status}@fruitbv.nl")
                ->call('corrigeren');

            $this->assertSame("houder-{$status}@fruitbv.nl", $gebruiker->fresh()->email, $status);
        }

        Mail::assertNothingSent();
    }

    public function test_een_uitnodiging_die_tussentijds_is_geaccepteerd_wordt_niet_meer_gecorrigeerd(): void
    {
        Mail::fake();
        $gebruiker = $this->uitgenodigde();

        $component = Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openCorrectieformulier', $gebruiker->id)
            ->set('correctieEmail', 'jan.jansen@fruitbv.nl');

        // Tussen het openen van de modal en het opslaan zit een tweede verzoek.
        $gebruiker->update(['status' => 'actief']);

        $component->call('corrigeren');

        $this->assertSame('tyfout@fruitbv.nl', $gebruiker->fresh()->email);
        Mail::assertNothingSent();
    }

    public function test_een_medewerker_mag_niet_corrigeren(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $gebruiker = $this->uitgenodigde();

        Livewire::actingAs($medewerker)
            ->test(GebruikersOverzicht::class)
            ->set('correctieGebruikerId', $gebruiker->id)
            ->set('correctieNaam', 'Jan Jansen')
            ->set('correctieEmail', 'jan.jansen@fruitbv.nl')
            ->call('corrigeren')
            ->assertForbidden();

        $this->assertSame('tyfout@fruitbv.nl', $gebruiker->fresh()->email);
    }

    // ── Botsing, normalisatie, ongewijzigd ───────────────────────────────────

    public function test_een_adres_dat_bij_een_ander_account_hoort_wordt_geweigerd_met_de_naam_erbij(): void
    {
        Mail::fake();
        $bezet = Gebruiker::factory()->gedeactiveerd()->create([
            'naam' => 'Piet Pietersen', 'email' => 'piet@fruitbv.nl',
        ]);
        $gebruiker = $this->uitgenodigde();

        $component = $this->corrigeer($gebruiker, 'Jan Jansen', 'piet@fruitbv.nl');

        $component->assertHasErrors('correctieEmail');
        $this->assertStringContainsString(
            $bezet->naam,
            $component->errors()->first('correctieEmail'),
            'De melding hoort te zeggen bij wie het adres hoort, anders gaat de CISO zoeken.',
        );

        $this->assertSame('tyfout@fruitbv.nl', $gebruiker->fresh()->email);
        Mail::assertNothingSent();
    }

    public function test_het_adres_wordt_genormaliseerd(): void
    {
        Mail::fake();
        $gebruiker = $this->uitgenodigde();

        $this->corrigeer($gebruiker, 'Jan Jansen', '  JAN@Fruitbv.NL ')->assertHasNoErrors();

        $this->assertSame('jan@fruitbv.nl', $gebruiker->fresh()->email);
    }

    public function test_ongewijzigd_opslaan_verstuurt_wel_maar_trekt_de_link_niet_in(): void
    {
        Mail::fake();
        $gebruiker = $this->uitgenodigde();
        $hash = $gebruiker->wachtwoord;

        $this->corrigeer($gebruiker, $gebruiker->naam, $gebruiker->email)->assertHasNoErrors();

        // Geen wijziging betekent geen rotatie: anders sterft de link zonder dat
        // er een auditregel tegenover staat (er blijft na het filteren van
        // `wachtwoord` niets over om te loggen).
        $this->assertSame($hash, $gebruiker->fresh()->wachtwoord);
        Mail::assertSent(GebruikerUitgenodigd::class);
    }

    // ── Audit trail ──────────────────────────────────────────────────────────

    public function test_de_auditregel_toont_oud_en_nieuw_adres_en_geen_wachtwoord(): void
    {
        Mail::fake();
        $gebruiker = $this->uitgenodigde();

        $this->corrigeer($gebruiker, 'Jan Jansen', 'jan.jansen@fruitbv.nl');

        $regel = AuditLogregel::where('entiteit_type', 'gebruiker')
            ->where('entiteit_id', $gebruiker->id)
            ->where('actie', 'gewijzigd')
            ->get()
            ->first(fn (AuditLogregel $r) => isset($r->nieuwe_waarde['email']));

        $this->assertNotNull($regel, 'De correctie hoort een leesbare auditregel op te leveren.');
        $this->assertSame('tyfout@fruitbv.nl', $regel->oude_waarde['email']);
        $this->assertSame('jan.jansen@fruitbv.nl', $regel->nieuwe_waarde['email']);
        $this->assertArrayNotHasKey('wachtwoord', $regel->nieuwe_waarde);
        $this->assertArrayNotHasKey('wachtwoord', $regel->oude_waarde);
    }

    // ── Het tijdstempel en het signaal ───────────────────────────────────────

    public function test_het_tijdstempel_wordt_gezet_bij_uitnodigen_opnieuw_versturen_en_corrigeren(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-01 10:00:00'));

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->set('naam', 'Nieuwe Medewerker')
            ->set('email', 'nieuw@fruitbv.nl')
            ->set('rolId', Rol::where('naam', 'Medewerker')->value('id'))
            ->call('uitnodigen')
            ->assertHasNoErrors();

        $gebruiker = Gebruiker::where('email', 'nieuw@fruitbv.nl')->firstOrFail();
        $this->assertTrue($gebruiker->uitnodiging_verstuurd_op->equalTo(Carbon::parse('2026-06-01 10:00:00')));

        Carbon::setTestNow(Carbon::parse('2026-06-05 10:00:00'));
        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('uitnodigingOpnieuwVersturen', $gebruiker->id);
        $this->assertTrue($gebruiker->fresh()->uitnodiging_verstuurd_op->equalTo(Carbon::parse('2026-06-05 10:00:00')));

        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));
        $this->corrigeer($gebruiker, 'Nieuwe Medewerker', 'beter@fruitbv.nl');
        $this->assertTrue($gebruiker->fresh()->uitnodiging_verstuurd_op->equalTo(Carbon::parse('2026-06-09 10:00:00')));

        Carbon::setTestNow();
    }

    public function test_een_mislukte_verzending_zet_het_tijdstempel_niet(): void
    {
        // De kolom registreert dat er post uit is gegaan, niet dat er op een knop
        // is gedrukt. Anders verdwijnt het signaal juist bij het account waar de
        // mail nooit aankwam.
        Mail::shouldReceive('to')->andThrow(new RuntimeException('mailserver weg'));

        $gebruiker = Gebruiker::factory()->uitgenodigd()->create(['uitnodiging_verstuurd_op' => null]);

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('uitnodigingOpnieuwVersturen', $gebruiker->id);

        $this->assertNull($gebruiker->fresh()->uitnodiging_verstuurd_op);
    }

    public function test_een_verlopen_uitnodiging_meldt_zichzelf_en_verdwijnt_na_opnieuw_versturen(): void
    {
        Mail::fake();
        $gebruiker = Gebruiker::factory()->uitgenodigd()->create([
            'uitnodiging_verstuurd_op' => now()->subDays(Uitnodiging::GELDIGHEID_DAGEN + 1),
        ]);

        $this->assertTrue($gebruiker->uitnodigingVerlopen());

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->assertViewHas('verlopenUitnodigingen', 1)
            ->assertSee('Uitnodigingen zonder resultaat');

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('uitnodigingOpnieuwVersturen', $gebruiker->id);

        $this->assertFalse($gebruiker->fresh()->uitnodigingVerlopen());
    }

    public function test_een_actief_account_telt_niet_mee_als_verlopen_uitnodiging(): void
    {
        // De drempel hangt aan de status, niet alleen aan de datum: een account
        // dat allang in gebruik is hoort niet als openstaande uitnodiging te
        // blijven staan.
        $actief = Gebruiker::factory()->create([
            'uitnodiging_verstuurd_op' => now()->subYear(),
        ]);

        $this->assertFalse($actief->uitnodigingVerlopen());
    }
}
