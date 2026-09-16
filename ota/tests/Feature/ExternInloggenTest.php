<?php

namespace Tests\Feature;

use App\Livewire\GebruikersOverzicht;
use App\Livewire\UitnodigingAccepteren;
use App\Mail\GebruikerUitgenodigd;
use App\Mail\KoppelingUitgereikt;
use App\Models\AuditLogregel;
use App\Models\ExterneIdentiteit;
use App\Models\Gebruiker;
use App\Models\Loginpoging;
use App\Models\Rol;
use App\Support\Oidc\Aanvraag;
use App\Support\Uitnodiging;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\NepIdp;
use Tests\TestCase;

/**
 * Inloggen via een externe identiteitsprovider (implementatie/01j §13, punten
 * 6–25). De client zelf staat in tests/Unit/OidcProviderTest.
 */
class ExternInloggenTest extends TestCase
{
    use RefreshDatabase;

    private NepIdp $idp;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->idp = new NepIdp;
    }

    // --- Hulpmethodes --------------------------------------------------------

    /** Een actief extern account met een koppeling op het standaardsubject. */
    private function gekoppeld(array $attributen = [], string $rol = 'Medewerker'): Gebruiker
    {
        $gebruiker = Gebruiker::factory()->extern()->metRol($rol)->create($attributen);

        ExterneIdentiteit::factory()->create([
            'gebruiker_id' => $gebruiker->id,
            'issuer' => NepIdp::ISSUER,
            'subject' => 'onderwerp-123',
            'idp_gebruikersnaam' => $gebruiker->email,
        ]);

        return $gebruiker->refresh();
    }

    /** De knop op het loginscherm indrukken en de terugkeer van de IdP naspelen. */
    private function logInExtern(array $claims = [])
    {
        $this->get(route('extern.doorsturen'))->assertRedirectContains('https://idp.voorbeeld.test/autoriseer');

        return $this->keerTerug($claims);
    }

    /** De IdP komt terug bij de aanvraag die nu in de sessie staat. */
    private function keerTerug(array $claims = [], array $query = [])
    {
        $aanvraag = Aanvraag::inSessie();
        $this->assertNotNull($aanvraag, 'Er staat geen aanvraag in de sessie.');

        $this->idp->geeftUit($this->idp->token($aanvraag, $claims));

        return $this->get(route('extern.callback', $query ?: $this->idp->terugkeer($aanvraag)));
    }

    private function laatstePoging(): ?Loginpoging
    {
        return Loginpoging::latest('id')->first();
    }

    private function metTweefactor(Gebruiker $gebruiker): Gebruiker
    {
        app(EnableTwoFactorAuthentication::class)($gebruiker);
        $gebruiker->refresh();
        app(ConfirmTwoFactorAuthentication::class)($gebruiker, app(Google2FA::class)->getCurrentOtp(decrypt($gebruiker->two_factor_secret)));

        return $gebruiker->refresh();
    }

    // --- Zonder configuratie (6) ----------------------------------------------

    public function test_zonder_configuratie_verandert_er_niets_zichtbaars(): void
    {
        config(['extern_inloggen.issuer' => null]);

        $this->get('/login')->assertOk()->assertDontSee('Inloggen met');
        $this->get('/auth/extern')->assertNotFound();
        $this->get('/auth/extern/callback')->assertNotFound();

        Livewire::actingAs($this->ciso)->test(GebruikersOverzicht::class)
            ->call('openUitnodigingsformulier')
            ->assertSet('inlogmethode', 'wachtwoord')
            ->assertDontSee('Inloggen met');
    }

    public function test_zonder_configuratie_is_extern_niet_te_kiezen(): void
    {
        config(['extern_inloggen.issuer' => null]);

        Livewire::actingAs($this->ciso)->test(GebruikersOverzicht::class)
            ->set([
                'naam' => 'Jan Jansen',
                'email' => 'jan@example.com',
                'rolId' => Rol::where('naam', 'Medewerker')->value('id'),
                'inlogmethode' => 'extern',
            ])
            ->call('uitnodigen')
            ->assertHasErrors('inlogmethode');
    }

    // --- Loginscherm (7) ------------------------------------------------------

    public function test_de_knop_op_het_loginscherm_is_een_gewone_link(): void
    {
        $html = $this->get('/login')->assertOk()->assertSee('Inloggen met Voorbeeld-IdP')->getContent();

        preg_match('#<a[^>]*href="'.preg_quote(route('extern.doorsturen'), '#').'"[^>]*>#', $html, $treffer);

        $this->assertNotEmpty($treffer, 'De knop naar de IdP ontbreekt.');
        $this->assertStringNotContainsString('wire:navigate', $treffer[0]);
    }

    // --- Weigeringen (8, 9) ---------------------------------------------------

    public function test_een_state_die_niet_klopt_wordt_geweigerd_en_vastgelegd(): void
    {
        $this->gekoppeld();
        $this->get(route('extern.doorsturen'));

        $this->keerTerug(query: ['state' => 'verzonnen', 'code' => 'x'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame('extern_geweigerd', $this->laatstePoging()->reden);
        $this->assertSame('extern', $this->laatstePoging()->methode);
        $this->assertSame('', $this->laatstePoging()->email_ingevoerd);
    }

    public function test_een_callback_zonder_aanvraag_wordt_geweigerd(): void
    {
        $this->get(route('extern.callback', ['state' => 'x', 'code' => 'y']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame('extern_geweigerd', $this->laatstePoging()->reden);
    }

    public function test_een_verlopen_aanvraag_wordt_geweigerd(): void
    {
        $this->gekoppeld();
        $this->get(route('extern.doorsturen'));
        $this->travel(11)->minutes();

        $this->keerTerug()->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame('extern_geweigerd', $this->laatstePoging()->reden);
    }

    public function test_annuleren_bij_de_idp_levert_geen_loginpoging_op(): void
    {
        $this->get(route('extern.doorsturen'));
        $aanvraag = Aanvraag::inSessie();

        $this->get(route('extern.callback', ['state' => $aanvraag->state, 'error' => 'access_denied']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, Loginpoging::count());
    }

    // --- Inloggen (13–16) -----------------------------------------------------

    public function test_inloggen_met_een_gekoppelde_identiteit(): void
    {
        $gebruiker = $this->gekoppeld();

        $antwoord = $this->logInExtern()->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($gebruiker);

        // Nooit "Aangemeld blijven" bij een extern account (§5.2).
        $this->assertEmpty(array_filter(
            $antwoord->headers->getCookies(),
            fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'),
        ));

        $poging = $this->laatstePoging();
        $this->assertTrue($poging->succesvol);
        $this->assertSame('extern', $poging->methode);
        $this->assertFalse($poging->mfa_bij_idp);
        $this->assertNotNull($gebruiker->externeIdentiteit->fresh()->laatst_gebruikt_op);
    }

    public function test_een_volgende_wachtwoordlogin_telt_niet_als_extern(): void
    {
        // Een afgebroken externe login laat de methode in de sessie staan; het
        // loginscherm ruimt die op (§5.3).
        $gebruiker = Gebruiker::factory()->metRol('Medewerker')->create(['wachtwoord' => 'een-lang-genoeg-wachtwoord']);
        session(['inloggen.methode' => 'extern']);

        Volt::test('auth.login')
            ->set(['email' => $gebruiker->email, 'password' => 'een-lang-genoeg-wachtwoord'])
            ->call('login');

        $this->assertSame('wachtwoord', $this->laatstePoging()->methode);
        $this->assertNull($this->laatstePoging()->mfa_bij_idp);
    }

    public function test_een_onbekende_identiteit_komt_niet_binnen(): void
    {
        $this->gekoppeld();

        $this->logInExtern(['sub' => 'iemand-anders', 'email' => 'onbekend@voorbeeld.test'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame('extern_onbekend', $this->laatstePoging()->reden);
        $this->assertSame('onbekend@voorbeeld.test', $this->laatstePoging()->email_ingevoerd);
    }

    public function test_een_identiteit_bij_een_wachtwoordaccount_is_geen_toestemming(): void
    {
        $gebruiker = $this->gekoppeld();
        $gebruiker->update(['inlogmethode' => 'wachtwoord']);

        $this->logInExtern()->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame('extern_onbekend', $this->laatstePoging()->reden);
    }

    public function test_een_geblokkeerd_of_gedeactiveerd_account_komt_niet_binnen(): void
    {
        $gebruiker = $this->gekoppeld();
        $gebruiker->blokkeer($this->ciso, 'Verdachte activiteit op de laptop');

        $antwoord = $this->logInExtern()->assertRedirect(route('login'));

        $this->assertGuest();
        $fout = session('errors')->first('email');
        $this->assertStringContainsString('geblokkeerd', $fout);
        $this->assertStringNotContainsString('Verdachte', $fout);
        $this->assertSame('status', $this->laatstePoging()->reden);

        $gebruiker->update(['status' => 'gedeactiveerd']);
        $this->logInExtern()->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_mislukte_wachtwoordpogingen_blokkeren_een_extern_account_niet(): void
    {
        $gebruiker = $this->gekoppeld();

        // Vijf: de drempel van de blokkade. Een zesde poging houdt de
        // rate-limiter van het loginscherm al tegen, en die komt niet in de lijst.
        foreach (range(1, 5) as $poging) {
            Volt::test('auth.login')
                ->set(['email' => $gebruiker->email, 'password' => "gok-nummer-{$poging}-lang-genoeg"])
                ->call('login');
        }

        $this->assertSame('actief', $gebruiker->fresh()->status);
        $this->assertSame(5, Loginpoging::where('gebruiker_id', $gebruiker->id)->where('succesvol', false)->count());
    }

    // --- Wat een extern account niet kan (17) ---------------------------------

    public function test_wachtwoord_vergeten_verstuurt_niets_voor_een_extern_account(): void
    {
        $gebruiker = $this->gekoppeld();
        Notification::fake();

        Volt::test('auth.forgot-password')
            ->set('email', $gebruiker->email)
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors();

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_een_oud_hersteltoken_zet_geen_wachtwoord_bij_een_extern_account(): void
    {
        $gebruiker = $this->gekoppeld();
        $hash = $gebruiker->wachtwoord;
        $token = Password::broker()->createToken($gebruiker);

        Volt::test('auth.reset-password', ['token' => $token])
            ->set([
                'email' => $gebruiker->email,
                'wachtwoord' => 'een-nieuw-lang-wachtwoord',
                'wachtwoord_bevestiging' => 'een-nieuw-lang-wachtwoord',
            ])
            ->call('resetPassword');

        $this->assertSame($hash, $gebruiker->fresh()->wachtwoord);
    }

    public function test_het_wachtwoordscherm_is_er_niet_voor_een_extern_account(): void
    {
        $gebruiker = $this->gekoppeld();

        $this->actingAs($gebruiker)->get('/settings/profile')
            ->assertOk()
            ->assertSee('Aangemeld via')
            ->assertDontSee(route('settings.password'));

        Volt::actingAs($gebruiker)->test('settings.password')
            ->assertRedirect(route('settings.profile'));
    }

    // --- Uitnodigen en koppelen (10–12) ---------------------------------------

    public function test_uitnodigen_met_de_idp_als_standaard(): void
    {
        Mail::fake();

        Livewire::actingAs($this->ciso)->test(GebruikersOverzicht::class)
            ->call('openUitnodigingsformulier')
            ->assertSet('inlogmethode', 'extern')
            ->assertSee('Inloggen met')
            ->set([
                'naam' => 'Jan Jansen',
                'email' => 'jan@voorbeeld.test',
                'rolId' => Rol::where('naam', 'Medewerker')->value('id'),
            ])
            ->call('uitnodigen')
            ->assertHasNoErrors();

        $gebruiker = Gebruiker::where('email', 'jan@voorbeeld.test')->firstOrFail();
        $this->assertTrue($gebruiker->isExtern());

        Mail::assertSent(GebruikerUitgenodigd::class, fn ($mail) => str_contains($mail->render(), 'Voorbeeld-IdP-account'));
    }

    public function test_de_uitnodiging_van_een_extern_account_koppelt_in_plaats_van_een_wachtwoord(): void
    {
        $gebruiker = Gebruiker::factory()->uitgenodigd()->extern()->metRol('Medewerker')->create();
        $token = Uitnodiging::token($gebruiker);

        Livewire::test(UitnodigingAccepteren::class, ['gebruiker' => $gebruiker, 'token' => $token])
            ->assertSet('stap', 'koppelen')
            ->assertSee('Aanmelden met Voorbeeld-IdP')
            ->assertDontSee('Wachtwoord bevestigen')
            ->call('opslaan')
            ->assertForbidden();
    }

    public function test_koppelen_via_de_uitnodiging(): void
    {
        config(['tweefactor.idp_dwingt_af' => true]);
        $gebruiker = Gebruiker::factory()->uitgenodigd()->extern()->metRol('Medewerker')->create(['email' => 'jan@voorbeeld.test']);
        $token = Uitnodiging::token($gebruiker);

        Livewire::test(UitnodigingAccepteren::class, ['gebruiker' => $gebruiker, 'token' => $token])
            ->call('koppelen')
            ->assertRedirectContains('https://idp.voorbeeld.test/autoriseer');

        $this->keerTerug(['sub' => 'nieuw-subject'])->assertRedirect(route('dashboard', absolute: false));

        $gebruiker->refresh();
        $this->assertSame('actief', $gebruiker->status);
        $this->assertNotNull($gebruiker->email_geverifieerd_op);
        $this->assertSame('nieuw-subject', $gebruiker->externeIdentiteit->subject);
        $this->assertSame('jan@voorbeeld.test', $gebruiker->externeIdentiteit->idp_gebruikersnaam);
        $this->assertAuthenticatedAs($gebruiker);

        // De oude uitnodigingslink is dood.
        $this->assertFalse(Uitnodiging::tokenIsGeldig($gebruiker, $token));

        // Op naam van de betrokkene, niet van "Systeem (geplande taak)": dit is
        // wat een auditor bij A.5.16 vraagt (as-built 16-09-2026).
        $regel = AuditLogregel::where('entiteit_type', 'externe_identiteit')->where('actie', 'aangemaakt')->firstOrFail();
        $this->assertSame($gebruiker->id, $regel->gebruiker_id);
        $this->assertSame($gebruiker->naam, $regel->gebruiker_naam);
    }

    public function test_koppelen_stuurt_naar_de_tweede_factor_als_die_vereist_is(): void
    {
        config(['tweefactor.afdwingen' => true, 'tweefactor.idp_dwingt_af' => false]);
        $gebruiker = Gebruiker::factory()->uitgenodigd()->extern()->metRol('Medewerker')->create();

        Livewire::test(UitnodigingAccepteren::class, ['gebruiker' => $gebruiker, 'token' => Uitnodiging::token($gebruiker)])
            ->call('koppelen');

        $this->keerTerug()->assertRedirect(route('settings.tweefactor'));
        $this->assertAuthenticatedAs($gebruiker->fresh());
    }

    public function test_koppelen_nadat_de_uitnodiging_is_gecorrigeerd_wordt_geweigerd(): void
    {
        $gebruiker = Gebruiker::factory()->uitgenodigd()->extern()->metRol('Medewerker')->create();

        Livewire::test(UitnodigingAccepteren::class, ['gebruiker' => $gebruiker, 'token' => Uitnodiging::token($gebruiker)])
            ->call('koppelen');

        // Tussen de klik en de terugkeer corrigeert de CISO het adres (01g).
        $gebruiker->corrigeerUitnodiging('Jan Jansen', 'jan.jansen@voorbeeld.test');

        $this->keerTerug()->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame('uitgenodigd', $gebruiker->fresh()->status);
        $this->assertSame(0, ExterneIdentiteit::count());
    }

    public function test_een_identiteit_die_al_bij_een_ander_account_hoort_wordt_niet_overschreven(): void
    {
        $bestaand = $this->gekoppeld();
        $nieuw = Gebruiker::factory()->uitgenodigd()->extern()->metRol('Medewerker')->create();

        Livewire::test(UitnodigingAccepteren::class, ['gebruiker' => $nieuw, 'token' => Uitnodiging::token($nieuw)])
            ->call('koppelen');

        $this->keerTerug()->assertRedirect(route('login'));

        $this->assertSame('uitgenodigd', $nieuw->fresh()->status);
        $this->assertSame($bestaand->id, ExterneIdentiteit::where('subject', 'onderwerp-123')->value('gebruiker_id'));
    }

    // --- Tweefactor (18–21) ---------------------------------------------------

    public function test_zonder_vrijstelling_geldt_de_tweefactorplicht_ook_voor_een_extern_account(): void
    {
        config(['tweefactor.afdwingen' => true, 'tweefactor.idp_dwingt_af' => false]);
        $gebruiker = $this->gekoppeld(['tweefactor_deadline' => now()->subDay()]);

        $this->actingAs($gebruiker)->get('/dashboard')->assertRedirect(route('settings.tweefactor'));

        // De callback blijft bereikbaar, anders is herbevestigen een lus (§4).
        $this->actingAs($gebruiker)->get(route('extern.callback'))
            ->assertRedirect(route('login'));
    }

    public function test_met_vrijstelling_hoeft_een_extern_account_niets_in_te_stellen(): void
    {
        config(['tweefactor.afdwingen' => true, 'tweefactor.idp_dwingt_af' => true]);
        $extern = $this->gekoppeld(['tweefactor_deadline' => now()->subDay()]);
        $wachtwoord = Gebruiker::factory()->metRol('Medewerker')->create(['tweefactor_deadline' => now()->subDay()]);

        $this->actingAs($extern)->get('/dashboard')->assertOk();
        $this->actingAs($wachtwoord)->get('/dashboard')->assertRedirect(route('settings.tweefactor'));

        $this->actingAs($extern)->get('/settings/tweefactor')
            ->assertOk()
            ->assertSee('wordt geregeld door')
            ->assertDontSee('Instellen');
    }

    public function test_een_externe_login_legt_de_vrijstelling_van_dat_moment_vast(): void
    {
        config(['tweefactor.idp_dwingt_af' => true]);
        $this->gekoppeld();

        $this->logInExtern();

        $this->assertTrue($this->laatstePoging()->mfa_bij_idp);
    }

    public function test_de_herinnering_slaat_vrijgestelde_accounts_over(): void
    {
        config(['tweefactor.afdwingen' => true, 'tweefactor.idp_dwingt_af' => true]);

        $this->gekoppeld(['tweefactor_deadline' => now()->addDays(2)]);
        Gebruiker::factory()->metRol('Medewerker')->create(['tweefactor_deadline' => now()->addDays(2)]);

        // Twee accounts met dezelfde deadline; alleen het wachtwoordaccount is in beeld.
        $this->artisan('isms:herinner-tweefactor')
            ->expectsOutputToContain('1 gebruiker(s) in beeld')
            ->assertSuccessful();
    }

    public function test_een_actieve_totp_koppeling_wordt_ook_bij_vrijstelling_gevraagd(): void
    {
        config(['tweefactor.idp_dwingt_af' => true]);
        $gebruiker = $this->metTweefactor($this->gekoppeld());

        $this->logInExtern()->assertRedirect(route('tweefactor.challenge'));

        $this->assertGuest();
        $this->assertSame($gebruiker->id, session('tweefactor.gebruiker_id'));
        $this->assertFalse(session('tweefactor.remember'));
    }

    public function test_herbevestigen_met_een_andere_identiteit_wordt_geweigerd(): void
    {
        config(['tweefactor.afdwingen' => true, 'tweefactor.idp_dwingt_af' => false]);
        $gebruiker = $this->gekoppeld();

        Volt::actingAs($gebruiker)->test('settings.tweefactor')
            ->call('inschakelen')
            ->assertHasErrors('herbevestigen')
            ->call('herbevestigen')
            ->assertRedirectContains('prompt=login');

        $this->actingAs($gebruiker)->keerTerug(['sub' => 'een-ander'])
            ->assertRedirect(route('settings.tweefactor'))
            ->assertSessionHas('fout');

        $this->assertNull(session('extern.aangemeld_op'));
    }

    public function test_herbevestigen_met_dezelfde_identiteit_vervangt_het_wachtwoord(): void
    {
        config(['tweefactor.afdwingen' => true, 'tweefactor.idp_dwingt_af' => false]);
        $gebruiker = $this->gekoppeld();

        Volt::actingAs($gebruiker)->test('settings.tweefactor')->call('herbevestigen');
        $this->actingAs($gebruiker)->keerTerug()->assertRedirect(route('settings.tweefactor'));

        Volt::actingAs($gebruiker)->test('settings.tweefactor')
            ->call('inschakelen')
            ->assertHasNoErrors()
            ->assertSet('toontQrCode', true);

        $this->assertNotNull($gebruiker->fresh()->two_factor_secret);
    }

    // --- Overzetten en herstel (22–25) ----------------------------------------

    public function test_een_wachtwoordaccount_overzetten_naar_de_idp(): void
    {
        Mail::fake();
        $gebruiker = Gebruiker::factory()->metRol('Medewerker')->create();
        Password::broker()->createToken($gebruiker);
        $hash = $gebruiker->wachtwoord;

        Livewire::actingAs($this->ciso)->test(GebruikersOverzicht::class)
            ->call('reikKoppelingUit', $gebruiker->id)
            ->assertHasNoErrors();

        $gebruiker->refresh();
        $this->assertTrue($gebruiker->isExtern());
        $this->assertNotSame($hash, $gebruiker->wachtwoord);
        $this->assertNotNull($gebruiker->koppeling_uitgereikt_op);
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $gebruiker->email)->count());
        $this->assertTrue(Uitnodiging::wachtOpKoppeling($gebruiker));

        Mail::assertSent(KoppelingUitgereikt::class, fn ($mail) => $mail->hasTo($gebruiker->email)
            && str_contains($mail->render(), '/koppeling/'));
    }

    public function test_de_koppellink_legt_een_nieuwe_koppeling(): void
    {
        config(['tweefactor.idp_dwingt_af' => true]);
        Mail::fake();
        $gebruiker = $this->gekoppeld();

        Livewire::actingAs($this->ciso)->test(GebruikersOverzicht::class)
            ->call('reikKoppelingUit', $gebruiker->id);

        $this->assertNull($gebruiker->fresh()->externeIdentiteit);

        // Met het oude account komt niemand meer binnen.
        auth()->logout();
        $this->logInExtern()->assertRedirect(route('login'));
        $this->assertGuest();

        $gebruiker->refresh();
        Livewire::test(UitnodigingAccepteren::class, ['gebruiker' => $gebruiker, 'token' => Uitnodiging::token($gebruiker)])
            ->assertSet('stap', 'koppelen')
            ->call('koppelen');

        $this->keerTerug(['sub' => 'nieuwe-laptop-account'])->assertRedirect(route('dashboard', absolute: false));

        $gebruiker->refresh();
        $this->assertSame('nieuwe-laptop-account', $gebruiker->externeIdentiteit->subject);
        $this->assertNull($gebruiker->koppeling_uitgereikt_op);
        $this->assertAuthenticatedAs($gebruiker);
    }

    public function test_de_koppellink_werkt_niet_meer_bij_een_gekoppeld_account(): void
    {
        $gebruiker = $this->gekoppeld();

        $this->get(Uitnodiging::link($gebruiker))->assertForbidden();
    }

    public function test_een_auditor_kan_geen_koppeling_uitreiken(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $gebruiker = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($auditor)->test(GebruikersOverzicht::class)
            ->call('reikKoppelingUit', $gebruiker->id)
            ->assertForbidden();

        $this->assertFalse($gebruiker->fresh()->isExtern());
    }

    public function test_zonder_mailkanaal_opent_de_modal_met_een_koppellink(): void
    {
        config(['mail.default' => 'log']);
        $gebruiker = Gebruiker::factory()->metRol('Medewerker')->create();

        $component = Livewire::actingAs($this->ciso)->test(GebruikersOverzicht::class)
            ->call('reikKoppelingUit', $gebruiker->id)
            ->assertSet('toontHandmatigeUitnodiging', true)
            ->assertSee('Koppellink handmatig uitreiken');

        $this->assertNull($gebruiker->fresh()->koppeling_uitgereikt_op);

        $component->call('downloadUitnodiging')->assertFileDownloaded();

        $this->assertNotNull($gebruiker->fresh()->koppeling_uitgereikt_op);
        $this->assertTrue(AuditLogregel::where('entiteit_type', 'gebruiker')
            ->where('entiteit_id', $gebruiker->id)
            ->where('nieuwe_waarde', 'like', '%koppellink als bestand%')
            ->exists());
    }

    public function test_het_gebruikersoverzicht_toont_de_inlogmethode_en_de_afwijking(): void
    {
        $gebruiker = $this->gekoppeld(['email' => 'jan@voorbeeld.test']);
        $gebruiker->externeIdentiteit->update(['idp_gebruikersnaam' => 'j.jansen@elders.test']);

        Livewire::actingAs($this->ciso)->test(GebruikersOverzicht::class)
            ->assertSee('j.jansen@elders.test')
            ->assertSee('Wijkt af van het uitnodigingsadres')
            ->assertSee('Koppeling opnieuw uitreiken');
    }

    public function test_terug_naar_wachtwoord_vanaf_de_commandoregel(): void
    {
        config(['tweefactor.afdwingen' => true]);
        $gebruiker = $this->gekoppeld();

        $this->artisan('isms:inlogmethode-wachtwoord', ['email' => $gebruiker->email])
            ->expectsOutputToContain('logt voortaan in met een wachtwoord')
            ->expectsOutputToContain('/reset-password/')
            ->assertSuccessful();

        $gebruiker->refresh();
        $this->assertFalse($gebruiker->isExtern());
        $this->assertNull($gebruiker->externeIdentiteit);
        $this->assertNotNull($gebruiker->tweefactor_deadline);
        $this->assertTrue(AuditLogregel::where('entiteit_type', 'gebruiker')
            ->where('entiteit_id', $gebruiker->id)
            ->where('nieuwe_waarde', 'like', '%commandoregel%')
            ->exists());
    }

    public function test_het_controlecommando(): void
    {
        $this->artisan('isms:extern-inloggen-controleren')
            ->expectsOutputToContain('Redirect-URI: '.route('extern.callback'))
            ->expectsOutputToContain('de issuer klopt')
            ->assertSuccessful();

        config(['extern_inloggen.issuer' => 'https://login.microsoftonline.com/common/v2.0']);
        $this->artisan('isms:extern-inloggen-controleren')->assertFailed();

        config(['extern_inloggen.issuer' => null]);
        $this->artisan('isms:extern-inloggen-controleren')
            ->expectsOutputToContain('niet ingesteld')
            ->assertSuccessful();
    }
}
