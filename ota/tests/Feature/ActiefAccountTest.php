<?php

namespace Tests\Feature;

use App\Models\Gebruiker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `VereistActiefAccount` op de web-groep (implementatie/01f §4).
 *
 * Zonder deze middleware wordt de status alleen op het loginscherm getoetst, en
 * werkt iemand die al ingelogd is gewoon door nadat zijn account geblokkeerd is
 * — precies het geval waarvoor de blokkade bedoeld is.
 */
class ActiefAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_actief_account_merkt_niets_van_de_middleware(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/dashboard')->assertOk();
        $this->assertAuthenticated();
    }

    public function test_geblokkeerd_tijdens_de_sessie_wordt_uitgelogd(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/dashboard')->assertOk();

        $gebruiker->blokkeer(door: null);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_gedeactiveerd_tijdens_de_sessie_wordt_uitgelogd(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/dashboard')->assertOk();

        $gebruiker->update(['status' => 'gedeactiveerd']);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * De reden van een handmatige blokkade is geschreven voor de CISO, over
     * iemand die op dat moment verdacht wordt. Hij hoort niet op het scherm van
     * de betrokkene (01f §5).
     */
    public function test_de_reden_van_de_blokkade_komt_niet_op_het_loginscherm(): void
    {
        $ciso = Gebruiker::factory()->create();
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/dashboard')->assertOk();

        $gebruiker->blokkeer($ciso, 'Vermoeden van gedeelde inloggegevens');

        $this->followingRedirects()
            ->get('/dashboard')
            ->assertDontSee('Vermoeden van gedeelde inloggegevens')
            ->assertSee('geblokkeerd');
    }

    /** Een uitgenodigd account hoort er ook niet in te kunnen blijven hangen. */
    public function test_uitgenodigd_account_wordt_uitgelogd(): void
    {
        $gebruiker = Gebruiker::factory()->uitgenodigd()->create();

        $this->actingAs($gebruiker)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
