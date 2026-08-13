<?php

namespace Tests\Feature;

use App\Livewire\BevestigAdreswijziging;
use App\Livewire\GebruikersOverzicht;
use App\Mail\AdreswijzigingAangevraagd;
use App\Mail\AdreswijzigingBevestigen;
use App\Models\AuditLogregel;
use App\Models\Gebruiker;
use App\Support\Adreswijziging;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Het e-mailadres van een actief account wijzigen (implementatie/01h).
 *
 * De test die het hele plan draagt is
 * {@see self::test_het_adres_verandert_niet_bij_het_aanvragen()}. Dat is de
 * inversie ten opzichte van 01g: bij een uitgenodigd account wijzigt het adres
 * direct, want er kan niets kapot. Bij een account dat in gebruik is zou
 * diezelfde directheid een typefout van de CISO laten uitmonden in een gebruiker
 * die niet meer kan inloggen, niet meer kan herstellen en geen notificaties meer
 * krijgt.
 *
 * De tweede die er echt toe doet is
 * {@see self::test_wachtwoord_en_sessie_overleven_de_wijziging()}: de verleiding
 * om `corrigeerUitnodiging()` te hergebruiken is groot, en die roteert het
 * wachtwoord.
 */
class AdreswijzigingTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();

        Mail::fake();
    }

    private function actiefAccount(string $email = 'oud@fruitbv.nl'): Gebruiker
    {
        return Gebruiker::factory()->metRol('Medewerker')->create([
            'naam' => 'Dana Wolters',
            'email' => $email,
            'status' => 'actief',
        ]);
    }

    private function vraagAan(Gebruiker $gebruiker, string $nieuw = 'nieuw@fruitbv.nl'): void
    {
        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openAdreswijziging', $gebruiker->id)
            ->set('adreswijzigingEmail', $nieuw)
            ->call('wijzigAdres');
    }

    public function test_het_adres_verandert_niet_bij_het_aanvragen(): void
    {
        $gebruiker = $this->actiefAccount();

        $this->vraagAan($gebruiker);

        $gebruiker->refresh();

        $this->assertSame('oud@fruitbv.nl', $gebruiker->email);
        $this->assertSame('nieuw@fruitbv.nl', $gebruiker->nieuw_email);
        $this->assertNotNull($gebruiker->nieuw_email_aangevraagd_op);
    }

    public function test_de_bevestigingslink_laat_het_adres_verhuizen(): void
    {
        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker);
        $gebruiker->refresh();

        $this->get(Adreswijziging::link($gebruiker))->assertOk();

        Livewire::test(BevestigAdreswijziging::class, [
            'gebruiker' => $gebruiker,
            'token' => Adreswijziging::token($gebruiker),
        ])->call('bevestigen');

        $gebruiker->refresh();

        $this->assertSame('nieuw@fruitbv.nl', $gebruiker->email);
        $this->assertNotNull($gebruiker->email_geverifieerd_op);
        $this->assertNull($gebruiker->nieuw_email);
        $this->assertNull($gebruiker->nieuw_email_aangevraagd_op);
    }

    public function test_de_link_werkt_maar_een_keer(): void
    {
        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker);
        $gebruiker->refresh();

        $link = Adreswijziging::link($gebruiker);
        $this->get($link)->assertOk();

        $gebruiker->bevestigAdreswijziging();

        $this->get($link)->assertForbidden();
    }

    public function test_een_tweede_aanvraag_doodt_de_eerste_link(): void
    {
        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker, 'eerste@fruitbv.nl');
        $gebruiker->refresh();

        $eersteLink = Adreswijziging::link($gebruiker);

        // Een seconde verder, anders is het aanvraagmoment gelijk en blijft het
        // token identiek als ook het adres hetzelfde zou zijn.
        Carbon::setTestNow(now()->addSecond());
        $this->vraagAan($gebruiker, 'tweede@fruitbv.nl');

        $this->get($eersteLink)->assertForbidden();
    }

    /**
     * De fout van 13-08-2026: twee keer op *Wijziging aanvragen* met hetzelfde
     * adres zette een nieuw aanvraagmoment, en omdat het token daaraan hangt was
     * de link die net verstuurd was meteen dood. De tweede keer hoort alleen
     * opnieuw te versturen.
     */
    public function test_dezelfde_aanvraag_herhalen_laat_de_eerste_link_leven(): void
    {
        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker);
        $gebruiker->refresh();

        $eersteLink = Adreswijziging::link($gebruiker);
        $eersteMoment = $gebruiker->nieuw_email_aangevraagd_op;

        Carbon::setTestNow(now()->addSeconds(4));
        $this->vraagAan($gebruiker, 'nieuw@fruitbv.nl');

        $gebruiker->refresh();

        $this->assertEquals($eersteMoment, $gebruiker->nieuw_email_aangevraagd_op,
            'Het aanvraagmoment mag niet opschuiven bij een herhaling.');
        $this->get($eersteLink)->assertOk();

        // Wel opnieuw verstuurd: twee bevestigingsmails, allebei met dezelfde
        // werkende link.
        Mail::assertSent(AdreswijzigingBevestigen::class, 2);
    }

    public function test_intrekken_doodt_de_link(): void
    {
        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker);
        $gebruiker->refresh();

        $link = Adreswijziging::link($gebruiker);

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('trekAdreswijzigingIn', $gebruiker->id);

        $gebruiker->refresh();

        $this->assertNull($gebruiker->nieuw_email);
        $this->get($link)->assertForbidden();
    }

    public function test_beide_berichten_gaan_naar_het_juiste_adres(): void
    {
        $gebruiker = $this->actiefAccount();

        $this->vraagAan($gebruiker);

        Mail::assertSent(AdreswijzigingBevestigen::class, fn ($mail) => $mail->hasTo('nieuw@fruitbv.nl'));
        Mail::assertSent(AdreswijzigingAangevraagd::class, fn ($mail) => $mail->hasTo('oud@fruitbv.nl'));
    }

    /**
     * Het bericht aan het oude adres noemt het nieuwe adres half: het domein
     * moet zichtbaar zijn om te kunnen beoordelen of de wijziging klopt, het
     * lokale deel voegt daar niets aan toe (01h §6).
     */
    public function test_het_oude_adres_krijgt_het_nieuwe_adres_gemaskeerd(): void
    {
        $this->assertSame('j••••••••n@nieuw.nl', Adreswijziging::gemaskeerd('jan.jansen@nieuw.nl'));
        $this->assertSame('••@nieuw.nl', Adreswijziging::gemaskeerd('jj@nieuw.nl'));

        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker, 'dana.wolters@nieuwbedrijf.nl');

        Mail::assertSent(AdreswijzigingAangevraagd::class, function ($mail) {
            $inhoud = $mail->render();

            return str_contains($inhoud, 'nieuwbedrijf.nl')
                && ! str_contains($inhoud, 'dana.wolters@nieuwbedrijf.nl');
        });
    }

    /**
     * Zonder `Auth::onceUsingId()` schrijft Auditeerbaar 'Systeem (geplande
     * taak)' — er is geen sessie op een publieke link (01h §4).
     */
    public function test_de_trail_noemt_de_gebruiker_bij_de_bevestiging(): void
    {
        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker);
        $gebruiker->refresh();

        Livewire::test(BevestigAdreswijziging::class, [
            'gebruiker' => $gebruiker,
            'token' => Adreswijziging::token($gebruiker),
        ])->call('bevestigen');

        $regel = AuditLogregel::where('entiteit_type', 'gebruiker')
            ->where('entiteit_id', $gebruiker->id)
            ->latest('id')->first();

        $this->assertNotNull($regel);
        $this->assertSame('Dana Wolters', $regel->gebruiker_naam);
        $this->assertNotSame('Systeem (geplande taak)', $regel->gebruiker_naam);
    }

    /** De aanvraag staat op naam van de CISO, de bevestiging op die van de houder. */
    public function test_aanvraag_en_bevestiging_zijn_twee_regels_in_de_trail(): void
    {
        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker);
        $gebruiker->refresh();

        $aanvraag = AuditLogregel::where('entiteit_id', $gebruiker->id)->latest('id')->first();
        $this->assertSame($this->ciso->naam, $aanvraag->gebruiker_naam);
        $this->assertArrayHasKey('nieuw_email', $aanvraag->nieuwe_waarde);

        Livewire::test(BevestigAdreswijziging::class, [
            'gebruiker' => $gebruiker,
            'token' => Adreswijziging::token($gebruiker),
        ])->call('bevestigen');

        $bevestiging = AuditLogregel::where('entiteit_id', $gebruiker->id)->latest('id')->first();
        $this->assertArrayHasKey('email', $bevestiging->nieuwe_waarde);
        $this->assertSame('nieuw@fruitbv.nl', $bevestiging->nieuwe_waarde['email']);
    }

    public function test_een_medewerker_mag_niet_wijzigen(): void
    {
        $gebruiker = $this->actiefAccount();
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($medewerker)
            ->test(GebruikersOverzicht::class)
            ->set('adreswijzigingGebruikerId', $gebruiker->id)
            ->set('adreswijzigingEmail', 'kaper@elders.nl')
            ->call('wijzigAdres')
            ->assertForbidden();

        Livewire::actingAs($medewerker)
            ->test(GebruikersOverzicht::class)
            ->call('trekAdreswijzigingIn', $gebruiker->id)
            ->assertForbidden();

        $this->assertNull($gebruiker->fresh()->nieuw_email);
    }

    public function test_aanvragen_op_een_niet_actief_account_doet_niets(): void
    {
        foreach (['uitgenodigd', 'geblokkeerd', 'gedeactiveerd'] as $status) {
            $gebruiker = Gebruiker::factory()->metRol('Medewerker')->create([
                'email' => "iemand-{$status}@fruitbv.nl",
                'status' => $status,
            ]);

            // Het id rechtstreeks zetten, zodat de statuscontrole ín `wijzigAdres()`
            // wordt geraakt en niet alleen de guard in `openAdreswijziging()`.
            Livewire::actingAs($this->ciso)
                ->test(GebruikersOverzicht::class)
                ->set('adreswijzigingGebruikerId', $gebruiker->id)
                ->set('adreswijzigingEmail', 'nieuw@fruitbv.nl')
                ->call('wijzigAdres');

            $this->assertNull($gebruiker->fresh()->nieuw_email, "status {$status} hoort niets te doen");
        }

        Mail::assertNothingSent();
    }

    public function test_de_modal_openen_bij_een_niet_actief_account_klapt_niet(): void
    {
        $uitgenodigd = Gebruiker::factory()->metRol('Medewerker')->create(['status' => 'uitgenodigd']);

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openAdreswijziging', $uitgenodigd->id)
            ->assertSet('toontAdreswijziging', false)
            // De null-guard in `wijzigAdres()`: zonder die regel liep
            // findOrFail(null) op een ModelNotFoundException (01g §13).
            ->call('wijzigAdres')
            ->assertOk();
    }

    public function test_een_adres_dat_al_bezet_is_wordt_geweigerd(): void
    {
        $gebruiker = $this->actiefAccount();
        Gebruiker::factory()->metRol('Auditor')->create([
            'naam' => 'Pieter de Vries',
            'email' => 'bezet@fruitbv.nl',
        ]);

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openAdreswijziging', $gebruiker->id)
            ->set('adreswijzigingEmail', 'bezet@fruitbv.nl')
            ->call('wijzigAdres')
            ->assertHasErrors('adreswijzigingEmail');

        $this->assertNull($gebruiker->fresh()->nieuw_email);
        Mail::assertNothingSent();
    }

    public function test_een_adres_dat_pas_na_de_aanvraag_bezet_raakt_wordt_bij_bevestiging_geweigerd(): void
    {
        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker);
        $gebruiker->refresh();

        $token = Adreswijziging::token($gebruiker);

        Gebruiker::factory()->metRol('Auditor')->create(['email' => 'nieuw@fruitbv.nl']);

        Livewire::test(BevestigAdreswijziging::class, [
            'gebruiker' => $gebruiker,
            'token' => $token,
        ])->call('bevestigen')->assertSet('bevestigd', false);

        $this->assertSame('oud@fruitbv.nl', $gebruiker->fresh()->email);
    }

    public function test_hetzelfde_adres_levert_geen_aanvraag_op(): void
    {
        $gebruiker = $this->actiefAccount();

        $this->vraagAan($gebruiker, 'oud@fruitbv.nl');

        $this->assertNull($gebruiker->fresh()->nieuw_email);
        Mail::assertNothingSent();
    }

    /**
     * De belangrijkste inversie ten opzichte van 01g: daar roteert het wachtwoord
     * omdat de uitnodigingslink moet sterven. Hier zou dat de rechtmatige
     * gebruiker buitensluiten.
     */
    public function test_wachtwoord_en_sessie_overleven_de_wijziging(): void
    {
        $gebruiker = $this->actiefAccount();
        $gebruiker->update(['wachtwoord' => 'GeheimGenoeg2026!']);
        $hashVoor = $gebruiker->fresh()->wachtwoord;

        $this->vraagAan($gebruiker);
        $gebruiker->refresh();

        Livewire::test(BevestigAdreswijziging::class, [
            'gebruiker' => $gebruiker,
            'token' => Adreswijziging::token($gebruiker),
        ])->call('bevestigen');

        $gebruiker->refresh();

        $this->assertSame($hashVoor, $gebruiker->wachtwoord);
        $this->assertTrue(auth()->validate([
            'email' => 'nieuw@fruitbv.nl',
            'password' => 'GeheimGenoeg2026!',
        ]));
    }

    public function test_een_verlopen_link_wordt_geweigerd(): void
    {
        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker);
        $gebruiker->refresh();

        $link = Adreswijziging::link($gebruiker);

        Carbon::setTestNow(now()->addDays(Adreswijziging::GELDIGHEID_DAGEN + 1));

        $this->get($link)->assertForbidden();
        $this->assertTrue($gebruiker->adreswijzigingVerlopen());
    }

    public function test_het_bevestigingsscherm_logt_niemand_in(): void
    {
        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker);
        $gebruiker->refresh();

        Livewire::test(BevestigAdreswijziging::class, [
            'gebruiker' => $gebruiker,
            'token' => Adreswijziging::token($gebruiker),
        ])->call('bevestigen')->assertSet('bevestigd', true);

        // Niet `assertGuest()`: dat leest de guard in dit proces, en die is door
        // `onceUsingId()` terecht gevuld — dat is precies de bedoeling, zodat de
        // audit trail de juiste naam krijgt. De vraag is of er iets blíjft staan,
        // en dat is een sessiesleutel. `once` hoort er geen te schrijven.
        $achtergebleven = array_filter(
            array_keys(session()->all()),
            fn (string $sleutel) => str_starts_with($sleutel, 'login_'),
        );

        $this->assertSame([], $achtergebleven, 'Er is een sessie achtergebleven na het bevestigen.');
    }

    public function test_een_verkeerd_token_wordt_geweigerd(): void
    {
        $gebruiker = $this->actiefAccount();
        $this->vraagAan($gebruiker);
        $gebruiker->refresh();

        $link = Adreswijziging::link($gebruiker);

        $this->get(str_replace(Adreswijziging::token($gebruiker), str_repeat('a', 64), $link))
            ->assertForbidden();
    }
}
