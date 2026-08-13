<?php

namespace Tests\Feature;

use App\Livewire\AuditLogOverzicht;
use App\Models\AuditLogregel;
use App\Models\Gebruiker;
use Carbon\CarbonImmutable;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Opslag in UTC, weergave in de lokale zone (implementatie/00o).
 *
 * De applicatie draait op UTC; op een Nederlandse server staat een tijdstip
 * daarmee één of twee uur achter de klok aan de muur. Zonder omzetting leest dat
 * als lokale tijd, en dat is voor een audit trail de gevaarlijke soort fout: wie
 * een regel naast een mail of een ticket legt, zit ernaast zonder aanwijzing dát
 * hij ernaast zit.
 */
class TijdweergaveTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();

        config()->set('tijd.weergave', 'Europe/Amsterdam');
    }

    /** De opslag blijft UTC: daar is de ketenhash overheen berekend. */
    public function test_de_macro_zet_om_zonder_de_opslag_te_raken(): void
    {
        $regel = $this->regel('2026-08-12 08:26:25');

        $this->assertSame('12-08-2026 10:26:25', $regel->tijdstip->lokaal()->format('d-m-Y H:i:s'));
        $this->assertSame('08:26:25', $regel->tijdstip->format('H:i:s'), 'De opgeslagen waarde hoort UTC te blijven.');
        $this->assertSame(
            '2026-08-12 08:26:25',
            (string) AuditLogregel::whereKey($regel->id)->value('tijdstip'),
        );
    }

    /**
     * `setTimezone()` op een mutable Carbon wijzigt het object zelf, en dat
     * object is het gecachete attribuut van het model. Zonder `copy()` in de
     * macro schuift elke volgende aanroep in dezelfde request opnieuw op.
     */
    public function test_twee_keer_omzetten_verschuift_niet(): void
    {
        $regel = $this->regel('2026-08-12 08:26:25');

        $eerste = $regel->tijdstip->lokaal()->format('H:i:s');
        $tweede = $regel->tijdstip->lokaal()->format('H:i:s');

        $this->assertSame($eerste, $tweede);
        $this->assertSame('10:26:25', $tweede);
    }

    public function test_winter_en_zomer_verschillen_een_uur(): void
    {
        $zomer = $this->regel('2026-08-12 08:00:00');
        $winter = $this->regel('2026-01-12 08:00:00');

        $this->assertSame('10:00', $zomer->tijdstip->lokaal()->format('H:i'), 'CEST is UTC+2.');
        $this->assertSame('09:00', $winter->tijdstip->lokaal()->format('H:i'), 'CET is UTC+1.');
    }

    public function test_het_scherm_toont_de_lokale_tijd_met_de_zone_erbij(): void
    {
        $this->regel('2026-08-12 08:26:25');

        Livewire::actingAs($this->ciso)
            ->test(AuditLogOverzicht::class)
            ->assertSee('12-08-2026 10:26:25')
            ->assertDontSee('12-08-2026 08:26:25')
            ->assertSee('Tijdstip (CEST)');
    }

    /**
     * De dagrand is de plek waar dit misgaat: een regel van 00:30 lokaal staat
     * als 22:30 de dag ervóór in de kolom. Met een filter op de ruwe datum viel
     * hij buiten "vanaf die dag", terwijl het scherm hem er wél op zet.
     */
    public function test_het_datumfilter_volgt_de_lokale_dag(): void
    {
        // 11-08 22:30 UTC = 12-08 00:30 in Amsterdam.
        $nachtelijk = $this->regel('2026-08-11 22:30:00');
        // 11-08 21:30 UTC = 11-08 23:30 in Amsterdam: nog de vorige dag.
        $ervoor = $this->regel('2026-08-11 21:30:00');

        $this->assertSame('12-08-2026', $nachtelijk->tijdstip->lokaal()->format('d-m-Y'));
        $this->assertSame('11-08-2026', $ervoor->tijdstip->lokaal()->format('d-m-Y'));

        Livewire::actingAs($this->ciso)
            ->test(AuditLogOverzicht::class)
            ->set('vanaf', '2026-08-12')
            ->assertSee('12-08-2026 00:30:00')
            ->assertDontSee('11-08-2026 23:30:00');
    }

    public function test_het_datumfilter_sluit_de_laatste_dag_volledig_in(): void
    {
        // 12-08 21:30 UTC = 12-08 23:30 lokaal: hoort nog bij de gekozen dag.
        $this->regel('2026-08-12 21:30:00');

        Livewire::actingAs($this->ciso)
            ->test(AuditLogOverzicht::class)
            ->set('tot', '2026-08-12')
            ->assertSee('12-08-2026 23:30:00');
    }

    /** Een tijdstip in UTC wegschrijven zoals de applicatie dat zelf doet. */
    private function regel(string $utc): AuditLogregel
    {
        Carbon::setTestNow($utc);

        $regel = AuditLogregel::create([
            'tijdstip' => CarbonImmutable::parse($utc),
            'gebruiker_id' => $this->ciso->id,
            'gebruiker_naam' => $this->ciso->naam,
            'blok_naam' => 'identity-access',
            'entiteit_type' => 'gebruiker',
            'entiteit_id' => $this->ciso->id,
            'entiteit_omschrijving' => $this->ciso->naam,
            'actie' => 'gewijzigd',
            'oude_waarde' => ['status' => 'uitgenodigd'],
            'nieuwe_waarde' => ['status' => 'actief'],
        ]);

        Carbon::setTestNow();

        return $regel;
    }
}
