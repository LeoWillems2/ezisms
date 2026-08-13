<?php

namespace Tests\Feature;

use App\Livewire\AssetDetail;
use App\Livewire\BeleidsdocumentDetail;
use App\Livewire\TakenOverzicht;
use App\Models\Asset;
use App\Models\Beleidsdocument;
use App\Models\Gebruiker;
use App\Models\Taak;
use App\Rules\KiesbareGebruiker;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Selects waarin een gebruiker gekozen wordt (eigenaar, toegewezene) tonen alleen
 * actieve accounts — je wijst geen werk toe aan iemand die niet kan inloggen.
 * Een al gekozen, inmiddels niet-actieve gebruiker blijft wél staan zodat een
 * bestaande toewijzing niet stilzwijgend verandert.
 */
class GebruikerKeuzelijstTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiesbaar_toont_alleen_actieve_accounts(): void
    {
        $actief = Gebruiker::factory()->create();
        $uitgenodigd = Gebruiker::factory()->uitgenodigd()->create();
        $geblokkeerd = Gebruiker::factory()->geblokkeerd()->create();
        $gedeactiveerd = Gebruiker::factory()->gedeactiveerd()->create();

        $ids = Gebruiker::kiesbaar()->pluck('id')->all();

        $this->assertContains($actief->id, $ids);
        $this->assertNotContains($uitgenodigd->id, $ids);
        $this->assertNotContains($geblokkeerd->id, $ids);
        $this->assertNotContains($gedeactiveerd->id, $ids);
    }

    public function test_kiesbaar_behoudt_een_al_gekozen_niet_actieve_gebruiker(): void
    {
        $actief = Gebruiker::factory()->create();
        $gedeactiveerd = Gebruiker::factory()->gedeactiveerd()->create();

        $ids = Gebruiker::kiesbaar($gedeactiveerd->id)->pluck('id')->all();

        $this->assertContains($gedeactiveerd->id, $ids);
        $this->assertContains($actief->id, $ids);
    }

    private function ruleSlaagt(KiesbareGebruiker $rule, int|string $waarde): bool
    {
        $gefaald = false;
        $rule->validate('eigenaarId', $waarde, function () use (&$gefaald) {
            $gefaald = true;
        });

        return ! $gefaald;
    }

    public function test_rule_weigert_niet_actief_maar_staat_actief_en_behoud_toe(): void
    {
        $actief = Gebruiker::factory()->create();
        $gedeactiveerd = Gebruiker::factory()->gedeactiveerd()->create();

        $this->assertTrue($this->ruleSlaagt(new KiesbareGebruiker, $actief->id));
        $this->assertFalse($this->ruleSlaagt(new KiesbareGebruiker, $gedeactiveerd->id));
        // Behoud: dezelfde niet-actieve id mag als hij al was opgeslagen.
        $this->assertTrue($this->ruleSlaagt(new KiesbareGebruiker($gedeactiveerd->id), $gedeactiveerd->id));
        // Leegte laat de rule met rust (required/nullable regelt dat).
        $this->assertTrue($this->ruleSlaagt(new KiesbareGebruiker, ''));
    }

    public function test_toewijzen_aan_gedeactiveerde_gebruiker_wordt_serverzijdig_geweigerd(): void
    {
        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $gedeactiveerd = Gebruiker::factory()->gedeactiveerd()->create();

        Livewire::actingAs($ciso)
            ->test(TakenOverzicht::class)
            ->call('nieuweTaak')
            ->set('titel', 'Iets doen')
            ->set('eigenaarId', (string) $gedeactiveerd->id)
            ->set('deadline', now()->addDays(5)->format('Y-m-d'))
            ->call('opslaan')
            ->assertHasErrors('eigenaarId');

        $this->assertDatabaseCount('taken', 0);
    }

    public function test_bewerken_behoudt_de_al_toegewezen_gedeactiveerde_eigenaar(): void
    {
        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $gedeactiveerd = Gebruiker::factory()->gedeactiveerd()->create();
        $taak = Taak::factory()->create([
            'eigenaar_id' => $gedeactiveerd->id,
            'deadline' => now()->addDays(5),
        ]);

        Livewire::actingAs($ciso)
            ->test(TakenOverzicht::class)
            ->call('bewerk', $taak->id)
            ->set('titel', 'Titel bijgewerkt')
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertSame('Titel bijgewerkt', $taak->fresh()->titel);
    }

    public function test_assetdetail_lekt_een_veld_behoud_niet_naar_het_andere(): void
    {
        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $actief = Gebruiker::factory()->create();
        $gedeactiveerd = Gebruiker::factory()->gedeactiveerd()->create();

        // Responsible is de gedeactiveerde gebruiker; accountable is actief.
        $asset = Asset::factory()->create([
            'accountable_id' => $actief->id,
            'responsible_id' => $gedeactiveerd->id,
        ]);

        Livewire::actingAs($ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            // De gedeactiveerde responsible hoort NIET in de accountable-lijst,
            ->assertViewHas('accountableGebruikers', fn (array $o) => ! array_key_exists($gedeactiveerd->id, $o))
            // maar WEL in de responsible-lijst (behoud van de huidige waarde).
            ->assertViewHas('responsibleGebruikers', fn (array $o) => array_key_exists($gedeactiveerd->id, $o))
            // en de toewijzing-select toont alleen actieve accounts.
            ->assertViewHas('gebruikers', fn (array $o) => ! array_key_exists($gedeactiveerd->id, $o));
    }

    public function test_beleiddetail_houdt_de_gedeactiveerde_eigenaar_maar_niet_andere(): void
    {
        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $oudEigenaar = Gebruiker::factory()->gedeactiveerd()->create();
        $andereGedeactiveerd = Gebruiker::factory()->gedeactiveerd()->create();

        $document = Beleidsdocument::factory()->create(['eigenaar_id' => $oudEigenaar->id]);

        Livewire::actingAs($ciso)
            ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document])
            ->assertViewHas('gebruikers', function (array $gebruikers) use ($ciso, $oudEigenaar, $andereGedeactiveerd) {
                return array_key_exists($ciso->id, $gebruikers)            // actief
                    && array_key_exists($oudEigenaar->id, $gebruikers)     // behouden (huidige eigenaar)
                    && ! array_key_exists($andereGedeactiveerd->id, $gebruikers); // eruit
            });
    }
}
