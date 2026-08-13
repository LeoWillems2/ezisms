<?php

namespace Tests\Feature;

use App\Livewire\GebruikersOverzicht;
use App\Livewire\UitnodigingAccepteren;
use App\Mail\GebruikerUitgenodigd;
use App\Models\AuditLogregel;
use App\Models\Gebruiker;
use App\Models\Rol;
use App\Support\Uitnodiging;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class GebruikersbeheerTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    public function test_ciso_kan_gebruiker_uitnodigen(): void
    {
        Mail::fake();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->set('naam', 'Nieuwe Medewerker')
            ->set('email', 'nieuw@example.com')
            ->set('rolId', Rol::where('naam', 'Medewerker')->value('id'))
            ->call('uitnodigen')
            ->assertHasNoErrors();

        $nieuw = Gebruiker::where('email', 'nieuw@example.com')->first();

        $this->assertNotNull($nieuw);
        $this->assertSame('uitgenodigd', $nieuw->status);
        $this->assertTrue($nieuw->heeftRol('Medewerker'));
        $this->assertSame($this->ciso->id, $nieuw->rolToewijzingen()->first()->toegekend_door_id);

        Mail::assertSent(GebruikerUitgenodigd::class);
    }

    public function test_auditor_mag_niet_uitnodigen(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        Livewire::actingAs($auditor)
            ->test(GebruikersOverzicht::class)
            ->set('naam', 'Stiekem')
            ->set('email', 'stiekem@example.com')
            ->set('rolId', Rol::where('naam', 'Medewerker')->value('id'))
            ->call('uitnodigen')
            ->assertForbidden();

        $this->assertDatabaseMissing('gebruikers', ['email' => 'stiekem@example.com']);
    }

    public function test_ciso_kan_deactiveren_maar_niet_het_eigen_account(): void
    {
        $doelwit = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('deactiveren', $doelwit)
            ->call('deactiveren', $this->ciso);

        $this->assertSame('gedeactiveerd', $doelwit->fresh()->status);
        $this->assertSame('actief', $this->ciso->fresh()->status);
    }

    public function test_ciso_kan_blokkade_opheffen(): void
    {
        $geblokkeerd = Gebruiker::factory()->geblokkeerd()->create();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('blokkadeOpheffen', $geblokkeerd);

        $this->assertSame('actief', $geblokkeerd->fresh()->status);
    }

    // --- Handmatig blokkeren (01f) ------------------------------------------

    public function test_ciso_kan_account_blokkeren_met_reden(): void
    {
        $doelwit = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openBlokkadeformulier', $doelwit)
            ->assertSet('toontBlokkadeformulier', true)
            ->set('blokkadeReden', 'Vermoeden van gedeelde inloggegevens')
            ->call('blokkeren')
            ->assertHasNoErrors()
            ->assertSet('toontBlokkadeformulier', false);

        $doelwit->refresh();

        $this->assertSame('geblokkeerd', $doelwit->status);
        $this->assertSame($this->ciso->id, $doelwit->geblokkeerd_door_id);
        $this->assertSame('Vermoeden van gedeelde inloggegevens', $doelwit->blokkade_reden);
        $this->assertNotNull($doelwit->geblokkeerd_op);
        $this->assertTrue($doelwit->blokkadeIsHandmatig());
    }

    public function test_blokkeren_zonder_reden_wordt_geweigerd(): void
    {
        $doelwit = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openBlokkadeformulier', $doelwit)
            ->set('blokkadeReden', '')
            ->call('blokkeren')
            ->assertHasErrors('blokkadeReden');

        $this->assertSame('actief', $doelwit->fresh()->status);
    }

    /**
     * De uitzondering uit p42: opheffen kan alléén een CISO, dus een enige CISO
     * die zichzelf blokkeert sluit zichzelf permanent buiten.
     */
    public function test_ciso_kan_zichzelf_niet_blokkeren(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openBlokkadeformulier', $this->ciso)
            ->assertSet('toontBlokkadeformulier', false);

        $this->assertSame('actief', $this->ciso->fresh()->status);
    }

    /** Ook niet door de modal op een ander te openen en dan het id te wisselen. */
    public function test_zelf_blokkeren_wordt_ook_bij_opslaan_geweigerd(): void
    {
        $doelwit = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openBlokkadeformulier', $doelwit)
            ->set('blokkadeGebruikerId', $this->ciso->id)
            ->set('blokkadeReden', 'Poging tot zelfblokkade')
            ->call('blokkeren')
            ->assertForbidden();

        $this->assertSame('actief', $this->ciso->fresh()->status);
    }

    public function test_alleen_een_actief_account_is_te_blokkeren(): void
    {
        $uitgenodigd = Gebruiker::factory()->uitgenodigd()->create();
        $gedeactiveerd = Gebruiker::factory()->gedeactiveerd()->create();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openBlokkadeformulier', $uitgenodigd)
            ->assertSet('toontBlokkadeformulier', false)
            ->call('openBlokkadeformulier', $gedeactiveerd)
            ->assertSet('toontBlokkadeformulier', false);

        $this->assertSame('uitgenodigd', $uitgenodigd->fresh()->status);
        $this->assertSame('gedeactiveerd', $gedeactiveerd->fresh()->status);
    }

    public function test_medewerker_mag_niet_blokkeren(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $doelwit = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($medewerker)
            ->test(GebruikersOverzicht::class)
            ->set('blokkadeGebruikerId', $doelwit->id)
            ->set('blokkadeReden', 'Zomaar')
            ->call('blokkeren')
            ->assertForbidden();

        $this->assertSame('actief', $doelwit->fresh()->status);
    }

    public function test_blokkade_en_reden_staan_in_de_audit_trail(): void
    {
        $doelwit = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openBlokkadeformulier', $doelwit)
            ->set('blokkadeReden', 'Lopend onderzoek naar datalek')
            ->call('blokkeren');

        // Actie `gewijzigd` en niet `status_gewijzigd`: die laatste zet
        // `Auditeerbaar` alléén als de status het énige gewijzigde veld was, en
        // hier gaan de reden en de blokkeerder mee in dezelfde regel. Dat is de
        // gedocumenteerde conventie (resources/kennisbank/de-audit-trail.md) —
        // de statusovergang is te vinden via de sleutel `status`, niet via de
        // actie. Eén regel met de reden erin is meer waard dan twee die apart
        // gelezen moeten worden.
        $regel = AuditLogregel::where('entiteit_type', 'gebruiker')
            ->where('entiteit_id', $doelwit->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->ciso->id, $regel->gebruiker_id);
        $this->assertSame('geblokkeerd', $regel->nieuwe_waarde['status']);
        $this->assertSame('Lopend onderzoek naar datalek', $regel->nieuwe_waarde['blokkade_reden']);
    }

    public function test_opheffen_wist_de_blokkadevelden(): void
    {
        $doelwit = Gebruiker::factory()->metRol('Medewerker')->create();
        $doelwit->blokkeer($this->ciso, 'Vermoeden van misbruik');

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('blokkadeOpheffen', $doelwit);

        $doelwit->refresh();

        $this->assertSame('actief', $doelwit->status);
        $this->assertNull($doelwit->geblokkeerd_op);
        $this->assertNull($doelwit->geblokkeerd_door_id);
        $this->assertNull($doelwit->blokkade_reden);
    }

    /**
     * De sessie moet meteen weg, niet pas bij het volgende verzoek. De
     * middleware is het vangnet, dit is de directe klap (01f §4).
     */
    public function test_blokkeren_beeindigt_de_lopende_sessies(): void
    {
        config()->set('session.driver', 'database');

        $doelwit = Gebruiker::factory()->metRol('Medewerker')->create();
        $ander = Gebruiker::factory()->metRol('Medewerker')->create();

        foreach ([$doelwit, $ander] as $gebruiker) {
            DB::table('sessions')->insert([
                'id' => 'sessie-'.$gebruiker->id,
                'user_id' => $gebruiker->id,
                'payload' => '',
                'last_activity' => now()->timestamp,
            ]);
        }

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openBlokkadeformulier', $doelwit)
            ->set('blokkadeReden', 'Vermoeden van misbruik')
            ->call('blokkeren');

        $this->assertDatabaseMissing('sessions', ['user_id' => $doelwit->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $ander->id]);
    }

    public function test_geldige_uitnodigingslink_toont_het_formulier(): void
    {
        $uitgenodigd = Gebruiker::factory()->uitgenodigd()->create();

        $this->get(Uitnodiging::link($uitgenodigd))
            ->assertOk()
            ->assertSee($uitgenodigd->email);
    }

    public function test_uitnodigingslink_met_vervalst_token_wordt_geweigerd(): void
    {
        $uitgenodigd = Gebruiker::factory()->uitgenodigd()->create();

        $link = Uitnodiging::link($uitgenodigd);
        $vervalst = str_replace(Uitnodiging::token($uitgenodigd), str_repeat('a', 64), $link);

        // De signature dekt het token af, dus knoeien levert al een 403 op.
        $this->get($vervalst)->assertForbidden();
    }

    public function test_uitnodigingslink_is_verbruikt_na_het_instellen_van_een_wachtwoord(): void
    {
        $uitgenodigd = Gebruiker::factory()->uitgenodigd()->create();
        $link = Uitnodiging::link($uitgenodigd);

        $uitgenodigd->update(['wachtwoord' => 'een-nieuw-wachtwoord', 'status' => 'actief']);

        $this->get($link)->assertForbidden();
    }

    public function test_verlopen_accounts_worden_gedeactiveerd(): void
    {
        $verlopen = Gebruiker::factory()->create(['vervalt_op' => now()->subDay()]);
        $looptNog = Gebruiker::factory()->create(['vervalt_op' => now()->addMonth()]);
        $zonderDatum = Gebruiker::factory()->create();

        $this->artisan('isms:verval-gebruikersaccounts')->assertSuccessful();

        $this->assertSame('gedeactiveerd', $verlopen->fresh()->status);
        $this->assertSame('actief', $looptNog->fresh()->status);
        $this->assertSame('actief', $zonderDatum->fresh()->status);
    }

    public function test_bootstrap_commando_maakt_een_actieve_ciso_aan(): void
    {
        $this->artisan('isms:eerste-ciso', [
            'email' => 'ciso@example.com',
            'wachtwoord' => 'geheim-genoeg',
            'naam' => 'Eerste CISO',
        ])->assertSuccessful();

        $nieuw = Gebruiker::where('email', 'ciso@example.com')->first();

        $this->assertSame('Eerste CISO', $nieuw->naam);
        $this->assertSame('actief', $nieuw->status);
        $this->assertTrue($nieuw->heeftRol('CISO'));
        $this->assertTrue($nieuw->magInloggen());
        // Het opgegeven wachtwoord werkt en is gehasht opgeslagen.
        $this->assertTrue(Hash::check('geheim-genoeg', $nieuw->wachtwoord));
    }

    public function test_bootstrap_commando_leidt_naam_af_uit_email(): void
    {
        $this->artisan('isms:eerste-ciso', [
            'email' => 'leo@lewi.nl',
            'wachtwoord' => 'geheim-genoeg',
        ])->assertSuccessful();

        $this->assertSame('Leo', Gebruiker::where('email', 'leo@lewi.nl')->value('naam'));
    }

    public function test_bootstrap_commando_weigert_een_te_kort_wachtwoord(): void
    {
        $this->artisan('isms:eerste-ciso', ['email' => 'kort@example.com', 'wachtwoord' => 'kort'])
            ->assertFailed();

        $this->assertNull(Gebruiker::where('email', 'kort@example.com')->first());
    }

    /**
     * De ondergrens ligt op twaalf tekens en nergens anders
     * (`Password::defaults()`, implementatie/01 §9). Elf mag niet, twaalf wel —
     * een test op de grens zelf, want een verschoven grens is anders pas te
     * merken als iemand er tegenaan loopt.
     */
    public function test_de_wachtwoordondergrens_ligt_op_twaalf_tekens(): void
    {
        $uitgenodigd = Gebruiker::factory()->uitgenodigd()->create();

        $formulier = fn (string $wachtwoord) => Livewire::test(UitnodigingAccepteren::class, [
            'gebruiker' => $uitgenodigd,
            'token' => Uitnodiging::token($uitgenodigd),
        ])
            ->set('wachtwoord', $wachtwoord)
            ->set('wachtwoord_bevestiging', $wachtwoord)
            ->call('opslaan');

        $formulier(str_repeat('a', 11))->assertHasErrors('wachtwoord');
        $this->assertSame('uitgenodigd', $uitgenodigd->fresh()->status);

        $formulier(str_repeat('a', 12))->assertHasNoErrors();
        $this->assertSame('actief', $uitgenodigd->fresh()->status);

        // Het accepteren ís het bewijs dat post op dat adres aankomt (01g §1).
        // Geen enkel scherm leest deze kolom vandaag; hij wordt getoetst omdat
        // het feit achteraf niet te reconstrueren is.
        $this->assertNotNull($uitgenodigd->fresh()->email_geverifieerd_op);
    }

    public function test_bootstrap_commando_weigert_een_dubbel_emailadres(): void
    {
        $this->artisan('isms:eerste-ciso', ['email' => $this->ciso->email, 'wachtwoord' => 'geheim-genoeg'])
            ->assertFailed();
    }
}
