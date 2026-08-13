<?php

namespace Tests\Feature;

use App\Livewire\GebruikersOverzicht;
use App\Livewire\UitnodigingAccepteren;
use App\Mail\TweefactorDeadline;
use App\Models\AuditLogregel;
use App\Models\Gebruiker;
use App\Models\Loginpoging;
use App\Models\Notificatieregel;
use App\Support\Uitnodiging;
use Database\Seeders\BlokSeeder;
use Database\Seeders\NotificatieregelSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Tweefactorauthenticatie (implementatie/01d).
 */
class TweefactorTest extends TestCase
{
    use RefreshDatabase;

    private const WACHTWOORD = 'een-lang-genoeg-wachtwoord';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
    }

    private function gebruiker(string $rol = 'Medewerker'): Gebruiker
    {
        return Gebruiker::factory()->metRol($rol)->create(['wachtwoord' => self::WACHTWOORD]);
    }

    /** Een gebruiker met bevestigde 2FA, plus de geldige code van dit moment. */
    private function metTweefactor(string $rol = 'Medewerker'): array
    {
        $gebruiker = $this->gebruiker($rol);

        app(EnableTwoFactorAuthentication::class)($gebruiker);
        $gebruiker->refresh();

        app(ConfirmTwoFactorAuthentication::class)($gebruiker, $this->huidigeCode($gebruiker));

        return [$gebruiker->refresh(), $this->huidigeCode($gebruiker)];
    }

    /** De code die de authenticator-app op dit moment zou tonen. */
    private function huidigeCode(Gebruiker $gebruiker): string
    {
        return app(Google2FA::class)->getCurrentOtp(decrypt($gebruiker->two_factor_secret));
    }

    // --- Instellen (§6) ----------------------------------------------------

    public function test_inschakelen_genereert_een_secret_maar_activeert_nog_niet(): void
    {
        $gebruiker = $this->gebruiker();

        Volt::actingAs($gebruiker)->test('settings.tweefactor')
            ->set('wachtwoord', self::WACHTWOORD)
            ->call('inschakelen')
            ->assertHasNoErrors();

        $gebruiker->refresh();

        $this->assertNotNull($gebruiker->two_factor_secret);
        $this->assertFalse(
            $gebruiker->tweefactorActief(),
            'Zonder bevestiging hoort 2FA niet actief te zijn — anders sluit een verkeerd gescande QR-code iemand buiten.',
        );
    }

    public function test_een_verkeerd_wachtwoord_levert_geen_secret_op(): void
    {
        $gebruiker = $this->gebruiker();

        Volt::actingAs($gebruiker)->test('settings.tweefactor')
            ->set('wachtwoord', 'iets-anders-en-lang-genoeg')
            ->call('inschakelen')
            ->assertHasErrors('wachtwoord');

        $this->assertNull($gebruiker->fresh()->two_factor_secret);
    }

    public function test_bevestigen_activeert_en_levert_herstelcodes(): void
    {
        $gebruiker = $this->gebruiker();

        $component = Volt::actingAs($gebruiker)->test('settings.tweefactor')
            ->set('wachtwoord', self::WACHTWOORD)
            ->call('inschakelen');

        $gebruiker->refresh();

        $component->set('code', $this->huidigeCode($gebruiker))
            ->call('bevestigen')
            ->assertHasNoErrors();

        $this->assertTrue($gebruiker->fresh()->tweefactorActief());
        $this->assertCount(8, $component->get('herstelcodes'));
    }

    public function test_bevestigen_met_een_foute_code_activeert_niets(): void
    {
        $gebruiker = $this->gebruiker();

        Volt::actingAs($gebruiker)->test('settings.tweefactor')
            ->set('wachtwoord', self::WACHTWOORD)
            ->call('inschakelen')
            ->set('code', '000000')
            ->call('bevestigen')
            ->assertHasErrors('code');

        $this->assertFalse($gebruiker->fresh()->tweefactorActief());
    }

    // --- Koppelen bij de uitnodiging (§13) ---------------------------------

    private function uitnodiging(): array
    {
        $gebruiker = Gebruiker::factory()->uitgenodigd()->create();

        return [$gebruiker, Livewire::test(UitnodigingAccepteren::class, [
            'gebruiker' => $gebruiker,
            'token' => Uitnodiging::token($gebruiker),
        ])];
    }

    /**
     * Een nieuwe gebruiker zit al achter zijn scherm met de uitnodiging open —
     * het goedkoopste moment om de app te koppelen. De respijtperiode blijft
     * bestaan voor accounts die er al wáren toen 2FA werd aangezet.
     */
    public function test_de_uitnodiging_gaat_na_het_wachtwoord_door_naar_de_tweede_factor(): void
    {
        [$gebruiker, $component] = $this->uitnodiging();

        $component->set('wachtwoord', self::WACHTWOORD)
            ->set('wachtwoord_bevestiging', self::WACHTWOORD)
            ->call('opslaan')
            ->assertHasNoErrors()
            ->assertSet('stap', 'tweefactor')
            ->assertNoRedirect();

        $gebruiker->refresh();

        // Het account is meteen bruikbaar, ook als hij hierna wegklikt: een
        // account dat pas ná het koppelen kan inloggen, is een account waar
        // niemand meer bij kan als het koppelen misgaat.
        $this->assertSame('actief', $gebruiker->status);
        $this->assertNotNull($gebruiker->two_factor_secret);
        $this->assertFalse($gebruiker->tweefactorActief());
    }

    public function test_de_uitnodiging_rondt_af_met_herstelcodes(): void
    {
        [$gebruiker, $component] = $this->uitnodiging();

        $component->set('wachtwoord', self::WACHTWOORD)
            ->set('wachtwoord_bevestiging', self::WACHTWOORD)
            ->call('opslaan');

        $component->set('code', $this->huidigeCode($gebruiker->refresh()))
            ->call('bevestigen')
            ->assertHasNoErrors()
            ->assertSet('stap', 'klaar');

        $this->assertTrue($gebruiker->fresh()->tweefactorActief());
        $this->assertCount(8, $component->get('herstelcodes'));
    }

    public function test_wie_zijn_telefoon_niet_bij_zich_heeft_valt_terug_op_de_respijtperiode(): void
    {
        [$gebruiker, $component] = $this->uitnodiging();

        $component->set('wachtwoord', self::WACHTWOORD)
            ->set('wachtwoord_bevestiging', self::WACHTWOORD)
            ->call('opslaan')
            ->call('laterInstellen')
            ->assertRedirect(route('login'));

        $gebruiker->refresh();

        $this->assertSame('actief', $gebruiker->status);
        $this->assertFalse($gebruiker->tweefactorActief());

        // De deadline wordt pas bij zijn eerste aanmelding gezet, door de
        // middleware — daar loopt de klok van iedereen.
        $this->assertNull($gebruiker->tweefactor_deadline);
    }

    public function test_zonder_afdwingen_blijft_de_uitnodiging_een_wachtwoordscherm(): void
    {
        config(['tweefactor.afdwingen' => false]);

        [$gebruiker, $component] = $this->uitnodiging();

        $component->set('wachtwoord', self::WACHTWOORD)
            ->set('wachtwoord_bevestiging', self::WACHTWOORD)
            ->call('opslaan')
            ->assertRedirect(route('login'));

        $this->assertNull($gebruiker->fresh()->two_factor_secret);
    }

    // --- Inloggen (§7) -----------------------------------------------------

    /**
     * De kern van de hele feature. Zonder deze test merkt niemand dat een
     * refactor de doorverwijzing overslaat en iedereen zonder tweede factor
     * binnenlaat.
     */
    public function test_wie_tweefactor_heeft_komt_na_het_wachtwoord_op_de_challenge(): void
    {
        [$gebruiker] = $this->metTweefactor();

        Volt::test('auth.login')
            ->set('email', $gebruiker->email)
            ->set('password', self::WACHTWOORD)
            ->call('login')
            ->assertRedirect(route('tweefactor.challenge'));

        $this->assertGuest('web');
    }

    public function test_een_geldige_code_logt_in_en_wordt_vastgelegd(): void
    {
        [$gebruiker, $code] = $this->metTweefactor();

        // Fortify onthoudt gebruikte codes in de cache en weigert ze een tweede
        // keer — terecht, maar in deze test is het bevestigen bij het instellen
        // net dezelfde code. Cache leeg = een verse aanmelding.
        Cache::flush();

        session(['tweefactor.gebruiker_id' => $gebruiker->id]);

        Volt::test('auth.tweefactor-challenge')
            ->set('code', $code)
            ->call('verifieren')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($gebruiker);
        $this->assertSame(1, Loginpoging::where('succesvol', true)->count());
        $this->assertNull(session('tweefactor.gebruiker_id'));
    }

    /**
     * Beslissing 4 uit §1, expliciet geborgd: een foute tweede factor blokkeert
     * het account niet. De verleiding is het `Failed`-event te hergebruiken, en
     * dat leidt via de bestaande listener rechtstreeks naar een blokkade.
     */
    public function test_een_foute_code_blokkeert_het_account_niet(): void
    {
        [$gebruiker] = $this->metTweefactor();

        session(['tweefactor.gebruiker_id' => $gebruiker->id]);

        Volt::test('auth.tweefactor-challenge')
            ->set('code', '000000')
            ->call('verifieren')
            ->assertHasErrors('code');

        $this->assertGuest('web');
        $this->assertSame('actief', $gebruiker->fresh()->status);

        $poging = Loginpoging::latest('id')->first();
        $this->assertFalse($poging->succesvol);
        $this->assertSame('totp', $poging->reden);
    }

    public function test_te_veel_foute_codes_sturen_terug_naar_login_zonder_blokkade(): void
    {
        [$gebruiker] = $this->metTweefactor();

        session(['tweefactor.gebruiker_id' => $gebruiker->id]);

        $component = Volt::test('auth.tweefactor-challenge');

        foreach (range(1, config('tweefactor.max_pogingen')) as $poging) {
            $component->set('code', '000000')->call('verifieren');
        }

        $component->set('code', '000000')
            ->call('verifieren')
            ->assertRedirect(route('login'));

        $this->assertGuest('web');
        $this->assertSame('actief', $gebruiker->fresh()->status);

        RateLimiter::clear('2fa|'.$gebruiker->id.'|127.0.0.1');
    }

    public function test_een_herstelcode_logt_in_en_is_daarna_verbruikt(): void
    {
        [$gebruiker] = $this->metTweefactor();

        $codes = $gebruiker->recoveryCodes();
        $gebruikt = $codes[0];

        session(['tweefactor.gebruiker_id' => $gebruiker->id]);

        Volt::test('auth.tweefactor-challenge')
            ->set('herstelcode', true)
            ->set('code', $gebruikt)
            ->call('verifieren')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($gebruiker);

        $nieuwe = $gebruiker->fresh()->recoveryCodes();
        $this->assertNotContains($gebruikt, $nieuwe);
        $this->assertCount(8, $nieuwe, 'Een verbruikte code wordt vervangen, niet weggehaald.');
    }

    /**
     * Fortify weigert een code die al is gebruikt. Dat is een eigenschap om
     * vast te leggen: wie de code van iemands scherm afleest, kan er niets meer
     * mee zodra die één keer langs is gekomen.
     */
    public function test_dezelfde_code_werkt_geen_tweede_keer(): void
    {
        [$gebruiker, $code] = $this->metTweefactor();
        Cache::flush();

        session(['tweefactor.gebruiker_id' => $gebruiker->id]);

        Volt::test('auth.tweefactor-challenge')
            ->set('code', $code)
            ->call('verifieren')
            ->assertHasNoErrors();

        auth()->logout();
        session(['tweefactor.gebruiker_id' => $gebruiker->id]);

        Volt::test('auth.tweefactor-challenge')
            ->set('code', $code)
            ->call('verifieren')
            ->assertHasErrors('code');

        $this->assertGuest('web');
    }

    public function test_de_challenge_zonder_sessiegegevens_stuurt_naar_login(): void
    {
        $this->get(route('tweefactor.challenge'))->assertRedirect(route('login'));
    }

    // --- Afdwingen (§9) ----------------------------------------------------

    public function test_zonder_deadline_krijgt_de_gebruiker_er_een_en_mag_door(): void
    {
        $gebruiker = $this->gebruiker();

        $this->actingAs($gebruiker)->get('/dashboard')->assertOk();

        $this->assertSame(
            now()->addDays(config('tweefactor.respijt_dagen'))->toDateString(),
            $gebruiker->fresh()->tweefactor_deadline->toDateString(),
        );
    }

    public function test_een_verstreken_deadline_stuurt_naar_het_instelscherm(): void
    {
        $gebruiker = $this->gebruiker();
        $gebruiker->forceFill(['tweefactor_deadline' => now()->subDay()])->save();

        $this->actingAs($gebruiker)->get('/dashboard')->assertRedirect(route('settings.tweefactor'));
    }

    public function test_het_instelscherm_en_uitloggen_blijven_bereikbaar(): void
    {
        $gebruiker = $this->gebruiker();
        $gebruiker->forceFill(['tweefactor_deadline' => now()->subDay()])->save();

        $this->actingAs($gebruiker)->get(route('settings.tweefactor'))->assertOk();
        $this->actingAs($gebruiker)->post(route('logout'))->assertRedirect('/');
    }

    public function test_zonder_afdwingen_blokkeert_er_niets(): void
    {
        config(['tweefactor.afdwingen' => false]);

        $gebruiker = $this->gebruiker();
        $gebruiker->forceFill(['tweefactor_deadline' => now()->subDay()])->save();

        $this->actingAs($gebruiker)->get('/dashboard')->assertOk();
    }

    // --- Herinnering per e-mail (§9) ---------------------------------------

    /** Een actief account zonder tweede factor, met een deadline over N dagen. */
    private function metDeadline(int $dagen): Gebruiker
    {
        $gebruiker = $this->gebruiker();
        $gebruiker->forceFill(['tweefactor_deadline' => now()->addDays($dagen)])->save();

        return $gebruiker;
    }

    public function test_de_herinnering_gaat_uit_vlak_voor_de_deadline(): void
    {
        Mail::fake();
        $this->seed(NotificatieregelSeeder::class);

        $bijna = $this->metDeadline(2);
        $nogLang = $this->metDeadline(10);

        $this->artisan('isms:herinner-tweefactor')->assertSuccessful();

        Mail::assertSent(TweefactorDeadline::class, 1);
        Mail::assertSent(
            TweefactorDeadline::class,
            fn (TweefactorDeadline $mail) => $mail->hasTo($bijna->email) && $mail->dagenResterend === 2,
        );
        Mail::assertNotSent(TweefactorDeadline::class, fn ($mail) => $mail->hasTo($nogLang->email));
    }

    /**
     * Veertien dezelfde mails is de snelste manier om een herinnering te leren
     * negeren — maar de mail ná het verstrijken is een andere fase en hoort er
     * wél te komen.
     */
    public function test_dezelfde_fase_mailt_niet_twee_keer(): void
    {
        Mail::fake();
        $this->seed(NotificatieregelSeeder::class);

        $this->metDeadline(2);

        $this->artisan('isms:herinner-tweefactor')->assertSuccessful();
        $this->artisan('isms:herinner-tweefactor')->assertSuccessful();

        Mail::assertSent(TweefactorDeadline::class, 1);

        // Drie dagen verder is de deadline verstreken. Bewust de klok verzetten
        // en niet de deadline: de dedupe kijkt naar wanneer er is gemaild ten
        // opzichte van de deadline, en die verhouding moet kloppen zoals ze in
        // het echt ontstaat.
        $this->travel(3)->days();

        $this->artisan('isms:herinner-tweefactor')->assertSuccessful();
        $this->artisan('isms:herinner-tweefactor')->assertSuccessful();

        Mail::assertSent(TweefactorDeadline::class, 2);
        Mail::assertSent(
            TweefactorDeadline::class,
            fn (TweefactorDeadline $mail) => $mail->dagenResterend === 0,
        );
    }

    public function test_wie_de_tweede_factor_al_heeft_krijgt_geen_herinnering(): void
    {
        Mail::fake();
        $this->seed(NotificatieregelSeeder::class);

        [$gebruiker] = $this->metTweefactor();
        $gebruiker->forceFill(['tweefactor_deadline' => now()->subDay()])->save();

        $this->artisan('isms:herinner-tweefactor')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_zonder_afdwingen_gaat_er_niets_uit(): void
    {
        config(['tweefactor.afdwingen' => false]);
        Mail::fake();
        $this->seed(NotificatieregelSeeder::class);

        $this->metDeadline(1);

        $this->artisan('isms:herinner-tweefactor')->assertSuccessful();

        Mail::assertNothingSent();
    }

    /** De CISO kan de regel uitzetten; dan mailt het systeem niet (blok 14 §5). */
    public function test_een_uitgezette_notificatieregel_onderdrukt_de_mail(): void
    {
        Mail::fake();
        $this->seed(NotificatieregelSeeder::class);
        Notificatieregel::where('gebeurtenis_type', 'tweefactor_deadline')->update(['actief' => false]);

        $this->metDeadline(1);

        $this->artisan('isms:herinner-tweefactor')->assertSuccessful();

        Mail::assertNothingSent();
    }

    // --- Herstel en trail (§8, §5) -----------------------------------------

    public function test_de_ciso_zet_de_tweefactor_terug(): void
    {
        [$gebruiker] = $this->metTweefactor();
        $ciso = $this->gebruiker('CISO');

        Livewire::actingAs($ciso)
            ->test(GebruikersOverzicht::class)
            ->call('tweefactorResetten', $gebruiker->id);

        $gebruiker->refresh();

        $this->assertFalse($gebruiker->tweefactorActief());
        $this->assertNull($gebruiker->two_factor_secret);
        $this->assertTrue($gebruiker->tweefactor_deadline->isFuture());

        $regel = AuditLogregel::where('entiteit_type', 'gebruiker')
            ->where('entiteit_id', $gebruiker->id)
            ->latest('id')
            ->first();

        $this->assertSame('gereset door de CISO', $regel->nieuwe_waarde['tweefactor']);
        $this->assertSame($ciso->id, $regel->gebruiker_id);
    }

    public function test_een_medewerker_kan_de_reset_niet_uitvoeren(): void
    {
        [$gebruiker] = $this->metTweefactor();

        Livewire::actingAs($this->gebruiker())
            ->test(GebruikersOverzicht::class)
            ->call('tweefactorResetten', $gebruiker->id)
            ->assertForbidden();

        $this->assertTrue($gebruiker->fresh()->tweefactorActief());
    }

    public function test_het_console_commando_zet_de_tweefactor_terug(): void
    {
        [$gebruiker] = $this->metTweefactor();

        $this->artisan('isms:tweefactor-resetten', ['email' => $gebruiker->email])
            ->assertSuccessful();

        $this->assertFalse($gebruiker->fresh()->tweefactorActief());
    }

    public function test_het_console_commando_meldt_een_onbekend_adres(): void
    {
        $this->artisan('isms:tweefactor-resetten', ['email' => 'bestaat@niet.nl'])->assertFailed();
    }

    public function test_het_instellen_komt_in_de_audit_trail(): void
    {
        [$gebruiker] = $this->metTweefactor();

        $waarden = AuditLogregel::where('entiteit_type', 'gebruiker')
            ->where('entiteit_id', $gebruiker->id)
            ->get()
            ->pluck('nieuwe_waarde.tweefactor')
            ->filter()
            ->all();

        $this->assertContains('ingesteld, nog niet bevestigd', $waarden);
        $this->assertContains('bevestigd en actief', $waarden);
    }

    /**
     * De borging van §4, op de ruwe kolominhoud en niet op een gerenderde
     * weergave: het secret is versleuteld met `APP_KEY`, maar het hoort niet
     * thuis in een tabel die de Auditor mag inzien én exporteren.
     */
    public function test_het_secret_en_de_herstelcodes_komen_niet_in_de_trail(): void
    {
        [$gebruiker] = $this->metTweefactor();
        $gebruiker->update(['naam' => 'Iets Anders']); // een gewone wijziging erbij

        $ruw = DB::table('audit_logregels')
            ->select('oude_waarde', 'nieuwe_waarde')
            ->get()
            ->map(fn ($regel) => $regel->oude_waarde.$regel->nieuwe_waarde)
            ->implode("\n");

        $this->assertNotEmpty($ruw);
        $this->assertStringNotContainsString('two_factor_secret', $ruw);
        $this->assertStringNotContainsString('two_factor_recovery_codes', $ruw);
        $this->assertStringNotContainsString($gebruiker->two_factor_secret, $ruw);
    }
}
