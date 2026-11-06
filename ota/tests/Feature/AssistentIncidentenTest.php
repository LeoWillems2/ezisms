<?php

namespace Tests\Feature;

use App\Mcp\IsmsServer;
use App\Mcp\Tools\Incidenten;
use App\Models\Afwijking;
use App\Models\Asset;
use App\Models\CorrigerendeMaatregel;
use App\Models\Gebruiker;
use App\Models\Grondoorzaak;
use App\Models\Incident;
use App\Models\IncidentMelding;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De tool `incidenten` (implementatie/14b §5, §12.2): dezelfde scopes als
 * IncidentDetail en AfwijkingDetail, en wat die schermen alleen met `muteren`
 * tonen, ontbreekt zonder dat recht ook hier.
 */
class AssistentIncidentenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);
    }

    public function test_medewerker_krijgt_dezelfde_rijen_als_de_schermscopes(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $collega = Gebruiker::factory()->metRol('Medewerker')->create();

        Incident::factory()->create(['gemeld_door_id' => $medewerker->id]);
        Incident::factory()->create(['gemeld_door_id' => $collega->id]);
        Incident::factory()->create(['gemeld_door_id' => $medewerker->id]);

        $eigen = Afwijking::factory()->create(['eigenaar_id' => $medewerker->id]);
        $viaMaatregel = Afwijking::factory()->create();
        CorrigerendeMaatregel::factory()->create(['afwijking_id' => $viaMaatregel->id, 'eigenaar_id' => $medewerker->id]);
        Afwijking::factory()->create(['eigenaar_id' => $collega->id]);

        $this->actingAs($medewerker);
        $verwachtIncidenten = Incident::query()->zichtbaar()->orderByDesc('gemeld_op')->pluck('id')->sort()->values()->all();
        $verwachtAfwijkingen = Afwijking::query()->zichtbaar()->pluck('id')->sort()->values()->all();
        $this->assertCount(2, $verwachtIncidenten);
        $this->assertSame([$eigen->id, $viaMaatregel->id], $verwachtAfwijkingen);

        IsmsServer::actingAs($medewerker)
            ->tool(Incidenten::class)
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($verwachtIncidenten, $verwachtAfwijkingen) {
                $json->where('incidenten.totaal', 2)
                    ->where('incidenten.rijen', fn ($r) => collect($r)->pluck('id')->sort()->values()->all() === $verwachtIncidenten)
                    ->where('afwijkingen.totaal', 2)
                    ->where('afwijkingen.rijen', fn ($r) => collect($r)->pluck('id')->sort()->values()->all() === $verwachtAfwijkingen)
                    // Zonder muteren geen link naar de afwijkingenlijst (die vraagt muteren).
                    ->missing('afwijkingen.url')
                    ->etc();
            });
    }

    public function test_medewerker_ziet_niet_wat_het_scherm_alleen_met_muteren_toont(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $incident = Incident::factory()->create([
            'gemeld_door_id' => $medewerker->id,
            'gekoppeld_asset_id' => Asset::factory()->create()->id,
            'raakt_persoonsgegevens' => true,
            'extern_meldingsplichtig' => true,
            'meldplicht_motivatie' => 'Grote kans op risico voor betrokkenen.',
        ]);
        IncidentMelding::factory()->teLaat()->create(['incident_id' => $incident->id]);
        Afwijking::factory()->create(['eigenaar_id' => $medewerker->id]);

        IsmsServer::actingAs($medewerker)
            ->tool(Incidenten::class)
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->where('incidenten.rijen.0.meldplicht.stand.code', 'meldingsplichtig')
                    ->missing('incidenten.rijen.0.meldplicht.motivatie')
                    ->missing('incidenten.rijen.0.meldplicht.kennisname_op')
                    ->missing('incidenten.rijen.0.asset')
                    // De verplichtingen staan wél voor iedereen op het scherm.
                    ->where('incidenten.rijen.0.meldplicht.verplichtingen.0.te_laat', true)
                    ->missing('afwijkingen.rijen.0.eigenaar')
                    ->etc();
            });
    }

    public function test_auditor_zonder_uitvoeren_wordt_geweigerd(): void
    {
        // De Auditor heeft op dit blok alleen lezen en exporteren; de routes vragen uitvoeren.
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        IsmsServer::actingAs($auditor)
            ->tool(Incidenten::class)
            ->assertHasErrors();
    }

    public function test_ciso_krijgt_meldplicht_maatregelen_en_filters(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $eigenaar = Gebruiker::factory()->create(['naam' => 'Jan Jansen']);

        $open = Incident::factory()->create([
            'titel' => 'Datalek klantportaal',
            'status' => 'in_onderzoek',
            'ernst' => 'hoog',
            'raakt_persoonsgegevens' => true,
            'extern_meldingsplichtig' => true,
            'meldplicht_motivatie' => 'Gegevens van klanten ingezien.',
        ]);
        IncidentMelding::factory()->create(['incident_id' => $open->id]);
        Incident::factory()->create(['status' => 'gesloten', 'gesloten_op' => now()]);

        $afwijking = Afwijking::factory()->create(['incident_id' => $open->id, 'bron' => 'incident', 'status' => 'actie_lopend', 'eigenaar_id' => $eigenaar->id]);
        Grondoorzaak::create(['afwijking_id' => $afwijking->id, 'omschrijving' => 'Geen toegangsreview']);
        CorrigerendeMaatregel::factory()->create([
            'afwijking_id' => $afwijking->id,
            'eigenaar_id' => $eigenaar->id,
            'deadline' => now()->subDay(),
            'omschrijving' => 'Kwartaalreview invoeren',
        ]);

        IsmsServer::actingAs($ciso)
            ->tool(Incidenten::class, ['open_only' => true])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($open, $afwijking) {
                $json->where('incidenten.totaal', 1)
                    ->where('incidenten.rijen.0.id', $open->id)
                    ->where('incidenten.rijen.0.status', ['code' => 'in_onderzoek', 'label' => 'In onderzoek'])
                    ->where('incidenten.rijen.0.ernst.code', 'hoog')
                    ->where('incidenten.rijen.0.meldplicht.motivatie', 'Gegevens van klanten ingezien.')
                    ->where('incidenten.rijen.0.meldplicht.verplichtingen.0.grondslag.code', 'avg')
                    ->where('incidenten.rijen.0.afwijkingen.0.url', route('afwijkingen.detail', $afwijking))
                    ->where('incidenten.rijen.0.url', route('incidenten.detail', $open))
                    ->where('afwijkingen.rijen.0.eigenaar', 'Jan Jansen')
                    ->where('afwijkingen.rijen.0.grondoorzaken.0.omschrijving', 'Geen toegangsreview')
                    ->where('afwijkingen.rijen.0.corrigerende_maatregelen.0.eigenaar', 'Jan Jansen')
                    ->where('afwijkingen.rijen.0.corrigerende_maatregelen.0.over_termijn', true)
                    ->where('afwijkingen.url', route('afwijkingen.index'))
                    ->etc();
            });
    }
}
