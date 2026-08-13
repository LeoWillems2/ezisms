<?php

namespace Tests\Feature;

use App\Models\Auditobject;
use App\Models\Maatregel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De live koppeling SoA → audit-universe (plan 11b §3): van-toepassing verklaren
 * laat het maatregel-object meteen verschijnen, zonder isms:sync-auditobjecten.
 */
class SoaRegelObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_van_toepassing_verklaren_maakt_meteen_een_actief_object(): void
    {
        $maatregel = Maatregel::factory()->create([
            'thema' => 'technologisch',
            'annex_a_referentie' => '8.8',
        ]);

        // Geen sync-command: de observer doet het werk bij het opslaan.
        $maatregel->soaRegel()->create(['van_toepassing' => true]);

        $object = Auditobject::where('soort', 'maatregel')
            ->where('maatregel_id', $maatregel->id)
            ->firstOrFail();

        $this->assertTrue($object->actief);
        $this->assertSame('A.8 Technologisch', $object->groep);
        $this->assertSame(8008, $object->volgorde);
    }

    public function test_uit_scope_halen_deactiveert_het_object(): void
    {
        $maatregel = Maatregel::factory()->create();
        $regel = $maatregel->soaRegel()->create(['van_toepassing' => true]);
        $this->assertTrue(Auditobject::where('maatregel_id', $maatregel->id)->value('actief'));

        $regel->update(['van_toepassing' => false]);

        $object = Auditobject::where('maatregel_id', $maatregel->id)->firstOrFail();
        $this->assertFalse($object->actief);
    }

    public function test_onbeslist_maakt_geen_object(): void
    {
        $maatregel = Maatregel::factory()->create();
        $maatregel->soaRegel()->create(['van_toepassing' => null]);

        $this->assertSame(0, Auditobject::where('maatregel_id', $maatregel->id)->count());
    }

    public function test_een_motivatie_edit_raakt_de_universe_niet(): void
    {
        $maatregel = Maatregel::factory()->create();
        $regel = $maatregel->soaRegel()->create(['van_toepassing' => false, 'motivatie' => 'oud']);

        // Geen object omdat de control niet in scope is.
        $this->assertSame(0, Auditobject::where('maatregel_id', $maatregel->id)->count());

        // Een niet-scope-mutatie mag geen object aanmaken of heractiveren.
        $regel->update(['motivatie' => 'nieuw']);

        $this->assertSame(0, Auditobject::where('maatregel_id', $maatregel->id)->count());
    }

    public function test_weer_in_scope_heractiveert_hetzelfde_object(): void
    {
        $maatregel = Maatregel::factory()->create();
        $regel = $maatregel->soaRegel()->create(['van_toepassing' => true]);
        $regel->update(['van_toepassing' => false]);

        $regel->update(['van_toepassing' => true]);

        // Eén object, weer actief — geen duplicaat.
        $this->assertSame(1, Auditobject::where('maatregel_id', $maatregel->id)->count());
        $this->assertTrue(Auditobject::where('maatregel_id', $maatregel->id)->value('actief'));
    }
}
