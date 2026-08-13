<?php

namespace Tests\Feature;

use App\Models\Gebruiker;
use App\Models\Loginpoging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_actieve_gebruiker_kan_inloggen_en_poging_wordt_vastgelegd(): void
    {
        $gebruiker = Gebruiker::factory()->create(['wachtwoord' => 'geheim-wachtwoord']);

        Volt::test('auth.login')
            ->set('email', $gebruiker->email)
            ->set('password', 'geheim-wachtwoord')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($gebruiker);
        $this->assertDatabaseHas('loginpogingen', [
            'gebruiker_id' => $gebruiker->id,
            'succesvol' => true,
        ]);
        $this->assertNotNull($gebruiker->fresh()->laatst_ingelogd_op);
    }

    public function test_geblokkeerd_account_kan_niet_inloggen(): void
    {
        $gebruiker = Gebruiker::factory()->geblokkeerd()->create(['wachtwoord' => 'geheim-wachtwoord']);

        Volt::test('auth.login')
            ->set('email', $gebruiker->email)
            ->set('password', 'geheim-wachtwoord')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    /**
     * De melding verschilt per bron van de blokkade (01f §5): "te veel mislukte
     * inlogpogingen" is onwaar zodra de CISO handmatig blokkeert. De reden zelf
     * blijft binnen — dit is de ene plek waar niet vaststaat dat de
     * rechthebbende meeleest.
     */
    public function test_melding_verschilt_tussen_handmatige_en_automatische_blokkade(): void
    {
        $ciso = Gebruiker::factory()->create();
        $handmatig = Gebruiker::factory()->create(['wachtwoord' => 'geheim-wachtwoord']);
        $handmatig->blokkeer($ciso, 'Vermoeden van gedeelde inloggegevens');

        $automatisch = Gebruiker::factory()->create(['wachtwoord' => 'geheim-wachtwoord']);
        $automatisch->blokkeer(door: null);

        $melding = fn (Gebruiker $g) => Volt::test('auth.login')
            ->set('email', $g->email)
            ->set('password', 'geheim-wachtwoord')
            ->call('login')
            ->errors()->first('email');

        $this->assertSame('Dit account is geblokkeerd. Neem contact op met de CISO.', $melding($handmatig));
        $this->assertStringContainsString('te veel mislukte inlogpogingen', $melding($automatisch));
    }

    public function test_gedeactiveerd_account_kan_niet_inloggen(): void
    {
        $gebruiker = Gebruiker::factory()->gedeactiveerd()->create(['wachtwoord' => 'geheim-wachtwoord']);

        Volt::test('auth.login')
            ->set('email', $gebruiker->email)
            ->set('password', 'geheim-wachtwoord')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_mislukte_poging_wordt_vastgelegd(): void
    {
        $gebruiker = Gebruiker::factory()->create(['wachtwoord' => 'geheim-wachtwoord']);

        Volt::test('auth.login')
            ->set('email', $gebruiker->email)
            ->set('password', 'fout-wachtwoord')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertSame(1, Loginpoging::where('email_ingevoerd', $gebruiker->email)
            ->where('succesvol', false)->count());
        $this->assertGuest();
    }

    public function test_account_wordt_geblokkeerd_na_vijf_mislukte_pogingen(): void
    {
        $gebruiker = Gebruiker::factory()->create(['wachtwoord' => 'geheim-wachtwoord']);

        // Vier eerdere mislukkingen binnen het venster; de vijfde zet de blokkade.
        Loginpoging::factory()->count(4)->create([
            'gebruiker_id' => $gebruiker->id,
            'email_ingevoerd' => $gebruiker->email,
            'succesvol' => false,
            'tijdstip' => now()->subMinutes(2),
        ]);

        Volt::test('auth.login')
            ->set('email', $gebruiker->email)
            ->set('password', 'fout-wachtwoord')
            ->call('login');

        $this->assertSame('geblokkeerd', $gebruiker->fresh()->status);
    }

    public function test_pogingen_buiten_het_tijdvenster_tellen_niet_mee(): void
    {
        $gebruiker = Gebruiker::factory()->create(['wachtwoord' => 'geheim-wachtwoord']);

        Loginpoging::factory()->count(4)->create([
            'gebruiker_id' => $gebruiker->id,
            'email_ingevoerd' => $gebruiker->email,
            'succesvol' => false,
            'tijdstip' => now()->subMinutes(20),
        ]);

        Volt::test('auth.login')
            ->set('email', $gebruiker->email)
            ->set('password', 'fout-wachtwoord')
            ->call('login');

        $this->assertSame('actief', $gebruiker->fresh()->status);
    }

    public function test_poging_met_onbekend_emailadres_wordt_gelogd_zonder_gebruiker(): void
    {
        Volt::test('auth.login')
            ->set('email', 'bestaat-niet@example.com')
            ->set('password', 'wat-dan-ook')
            ->call('login')
            ->assertHasErrors('email');

        $poging = Loginpoging::where('email_ingevoerd', 'bestaat-niet@example.com')->first();

        $this->assertNotNull($poging);
        $this->assertNull($poging->gebruiker_id);
    }

    public function test_er_is_geen_publieke_registratieroute(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
