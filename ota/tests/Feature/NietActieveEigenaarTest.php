<?php

namespace Tests\Feature;

use App\Livewire\AssetDetail;
use App\Livewire\AssetsOverzicht;
use App\Livewire\BeleidsdocumentenOverzicht;
use App\Livewire\RisicosOverzicht;
use App\Livewire\TakenOverzicht;
use App\Models\Asset;
use App\Models\Beleidsdocument;
use App\Models\Gebruiker;
use App\Models\Risico;
use App\Models\Taak;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Een eigenaar/toegewezene die niet (meer) actief is, blijft staan maar wordt
 * als op te ruimen gap gesignaleerd: een badge bij de naam en een teller op het
 * overzicht.
 */
class NietActieveEigenaarTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    private Gebruiker $gedeactiveerd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->gedeactiveerd = Gebruiker::factory()->gedeactiveerd()->create();
    }

    public function test_asset_teller_en_detailwaarschuwing(): void
    {
        $asset = Asset::factory()->create(['responsible_id' => $this->gedeactiveerd->id]);
        Asset::factory()->create(['accountable_id' => $this->ciso->id]); // actief: telt niet

        Livewire::actingAs($this->ciso)
            ->test(AssetsOverzicht::class)
            ->assertViewHas('metNietActieveEigenaar', 1);

        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->assertSee('wijs een actief account aan');
    }

    public function test_risico_teller_en_badge_in_lijst(): void
    {
        Risico::factory()->create([
            'titel' => 'Verweesd risico',
            'risico_eigenaar_id' => $this->gedeactiveerd->id,
        ]);

        Livewire::actingAs($this->ciso)
            ->test(RisicosOverzicht::class)
            ->assertViewHas('metNietActieveEigenaar', 1)
            ->assertSee('Gedeactiveerd'); // de statusbadge bij de naam
    }

    public function test_taken_teller_telt_alleen_openstaand(): void
    {
        Taak::factory()->create([
            'eigenaar_id' => $this->gedeactiveerd->id,
            'status' => 'open',
            'deadline' => now()->addDays(5),
        ]);
        // Voltooide taak bij dezelfde gebruiker telt niet: er valt niets meer te doen.
        Taak::factory()->create([
            'eigenaar_id' => $this->gedeactiveerd->id,
            'status' => 'voltooid',
            'deadline' => now()->subDays(5),
        ]);

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->assertViewHas('metNietActieveEigenaar', 1);
    }

    public function test_beleid_teller(): void
    {
        Beleidsdocument::factory()->create([
            'status' => 'actief',
            'eigenaar_id' => $this->gedeactiveerd->id,
        ]);

        Livewire::actingAs($this->ciso)
            ->test(BeleidsdocumentenOverzicht::class)
            ->assertViewHas('metNietActieveEigenaar', 1);
    }

    public function test_geen_teller_bij_actieve_eigenaar(): void
    {
        Risico::factory()->create(['risico_eigenaar_id' => $this->ciso->id]);

        Livewire::actingAs($this->ciso)
            ->test(RisicosOverzicht::class)
            ->assertViewHas('metNietActieveEigenaar', 0);
    }
}
