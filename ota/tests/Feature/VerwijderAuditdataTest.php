<?php

namespace Tests\Feature;

use App\Models\Afwijking;
use App\Models\AuditLogregel;
use App\Models\Auditobject;
use App\Models\Auditplan;
use App\Models\Auditprogramma;
use App\Models\AuditprogrammaDekking;
use App\Models\Auditronde;
use App\Models\Bevinding;
use App\Models\Bewijsstuk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VerwijderAuditdataTest extends TestCase
{
    use RefreshDatabase;

    /** Bouwt een volledige auditcyclus met ronde, bevinding, dekking en pivots. */
    private function bouwAuditdata(): Bevinding
    {
        $programma = Auditprogramma::factory()->create(['start_datum' => '2026-01-01', 'aantal_jaren' => 3]);
        $plan = Auditplan::factory()->voorProgramma($programma)->create();
        $ronde = Auditronde::factory()->create(['auditplan_id' => $plan->id, 'type' => 'intern']);
        $object = Auditobject::factory()->create();

        $ronde->auditobjecten()->attach($object->id);
        AuditprogrammaDekking::create([
            'auditprogramma_id' => $programma->id,
            'auditobject_id' => $object->id,
            'interval_jaren' => 3,
            'gepland_start_programmajaar' => 1,
        ]);
        DB::table('bewijs_koppelingen')->insert([
            'bewijsstuk_id' => Bewijsstuk::factory()->create()->id,
            'blok_naam' => 'auditmanagement',
            'entiteit_type' => 'auditronde',
            'entiteit_id' => $ronde->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Bevinding::factory()->create(['auditronde_id' => $ronde->id]);
    }

    public function test_dry_run_zonder_bevestig_verwijdert_niets(): void
    {
        $this->bouwAuditdata();

        $this->artisan('isms:verwijder-auditdata')
            ->expectsConfirmation('Alle bovenstaande auditdata onherroepelijk verwijderen?', 'no')
            ->expectsOutputToContain('Niets verwijderd')
            ->assertSuccessful();

        $this->assertSame(1, Auditprogramma::count());
        $this->assertSame(1, Auditronde::count());
        $this->assertSame(1, Bevinding::count());
    }

    public function test_bevestig_wist_de_kerndata_maar_behoudt_trail_en_universe(): void
    {
        $this->bouwAuditdata();
        // Het opbouwen logt zelf al auditmanagement-trail; meet die vóór het wissen.
        $trailVoor = AuditLogregel::where('blok_naam', 'auditmanagement')->count();
        $this->assertGreaterThan(0, $trailVoor);

        $this->artisan('isms:verwijder-auditdata', ['--bevestig' => true])->assertSuccessful();

        $this->assertSame(0, Auditprogramma::count());
        $this->assertSame(0, Auditplan::count());
        $this->assertSame(0, Auditronde::count());
        $this->assertSame(0, Bevinding::count());
        $this->assertSame(0, AuditprogrammaDekking::count());
        $this->assertSame(0, DB::table('auditronde_auditobject')->count());
        $this->assertSame(0, DB::table('bewijs_koppelingen')->where('entiteit_type', 'auditronde')->count());

        // Standaard behouden: de universe én de volledige trail (ongemoeid).
        $this->assertSame(1, Auditobject::count());
        $this->assertSame($trailVoor, AuditLogregel::where('blok_naam', 'auditmanagement')->count());
    }

    public function test_met_universe_wist_ook_de_auditobjecten(): void
    {
        $this->bouwAuditdata();

        $this->artisan('isms:verwijder-auditdata', ['--bevestig' => true, '--met-universe' => true])
            ->assertSuccessful();

        $this->assertSame(0, Auditobject::count());
    }

    public function test_met_trail_wist_alleen_de_auditmanagement_trail(): void
    {
        $this->bouwAuditdata();
        AuditLogregel::legVerzamelingVast(blokNaam: 'auditmanagement', entiteitType: 'auditronde', actie: 'aangemaakt', omschrijving: 'a');
        AuditLogregel::legVerzamelingVast(blokNaam: 'risico-soa', entiteitType: 'soa_regel', actie: 'gewijzigd', omschrijving: 'b');

        $this->artisan('isms:verwijder-auditdata', ['--bevestig' => true, '--met-trail' => true])
            ->assertSuccessful();

        $this->assertSame(0, AuditLogregel::where('blok_naam', 'auditmanagement')->count());
        // De trail van andere blokken blijft ongemoeid.
        $this->assertSame(1, AuditLogregel::where('blok_naam', 'risico-soa')->count());
    }

    public function test_waarschuwt_voor_afwijkingen_uit_bevindingen_en_behoudt_ze(): void
    {
        $bevinding = $this->bouwAuditdata();
        $afwijking = Afwijking::create([
            'bron' => 'audit_bevinding',
            'bevinding_id' => $bevinding->id,
            'omschrijving' => 'NC',
        ]);

        $this->artisan('isms:verwijder-auditdata', ['--bevestig' => true])
            ->expectsOutputToContain('afwijking')
            ->assertSuccessful();

        // De afwijking blijft, maar haar bron-bevinding is genulld (nullOnDelete).
        $this->assertTrue(Afwijking::whereKey($afwijking->id)->exists());
        $this->assertNull(Afwijking::find($afwijking->id)->bevinding_id);
    }

    public function test_meldt_niets_te_doen_bij_lege_database(): void
    {
        $this->artisan('isms:verwijder-auditdata', ['--bevestig' => true])
            ->expectsOutputToContain('Geen auditdata gevonden')
            ->assertSuccessful();
    }
}
