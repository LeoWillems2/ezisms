<?php

namespace App\Livewire;

use App\Mail\StapActueel;
use App\Models\Gebruiker;
use App\Models\Systeem;
use App\Models\Taak;
use App\Models\Wijziging;
use App\Models\Wijzigingssjabloon;
use App\Rules\KiesbareGebruiker;
use App\Support\Koppeling;
use App\Support\NotificatieDispatcher;
use App\Support\StapGeblokkeerd;
use App\Support\Stappenreeks;
use App\Support\Wijzigingsdossier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Het werkscherm van één wijzigingsdossier (implementatie/15 §8): de
 * dossiervelden, de hele stappenreeks, het bewijs en de evaluatie.
 *
 * Dit is het enige scherm dat de reeks in zijn geheel toont. Op `/taken` ziet
 * een Medewerker alleen zijn eigen stappen (07b §11) — bewust, maar het maakt
 * dit scherm nodig om te zien waar een dossier op wacht.
 */
#[Layout('components.layouts.app')]
class WijzigingDetail extends Component
{
    private const BLOK = 'wijzigingsbeheer';

    public Wijziging $wijziging;

    // In behandeling nemen.
    public string $sjabloonId = '';

    public ?string $geplandOp = null;

    // Dossiervelden.
    public string $terugvalplan = '';

    public string $impactToelichting = '';

    public string $externeReferentie = '';

    /** @var list<int> */
    public array $systeemIds = [];

    // Afsluiten.
    public bool $toontAfsluiten = false;

    public bool $geslaagd = true;

    public bool $teruggedraaid = false;

    public string $evaluatie = '';

    public function mount(Wijziging $wijziging): void
    {
        $this->wijziging = $wijziging;
        $this->terugvalplan = $wijziging->terugvalplan ?? '';
        $this->impactToelichting = $wijziging->impact_toelichting ?? '';
        $this->externeReferentie = $wijziging->externe_referentie ?? '';
        $this->systeemIds = $wijziging->systemen->pluck('id')->all();
        $this->geplandOp = ($wijziging->gepland_op ?? now()->addDays(14))->format('Y-m-d');
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', [self::BLOK, 'muteren']);
    }

    /**
     * De reeks, élke keer vers uit de database.
     *
     * Bewust géén computed property (`getStappenProperty`): die wordt binnen één
     * request gecachet, en dat brak dit scherm op twee manieren. Na een
     * geblokkeerde stap stond de status in het geheugen al op `voltooid` — de
     * `update()` had de attributen gezet vóórdat de observer gooide — terwijl er
     * niets was opgeslagen. En na een geslaagde stap toonde de cache nog de oude
     * reeks, zodat de volgende stap pas na een herlaadactie actueel leek.
     *
     * Een paar extra queries per request is hier de goedkopere kant van die
     * ruil: het scherm toont de stand van zaken, niet die van het begin van het
     * request.
     *
     * @return Collection<int, Taak>
     */
    private function stappen(): Collection
    {
        return Stappenreeks::voorEntiteit($this->wijziging)->load('eigenaar');
    }

    // --- Dossier -----------------------------------------------------------

    public function dossierOpslaan(): void
    {
        $this->vereisMuteerbaar();

        $gevalideerd = $this->validate([
            'terugvalplan' => ['nullable', 'string'],
            'impactToelichting' => ['nullable', 'string'],
            'externeReferentie' => ['nullable', 'string', 'max:255'],
            'systeemIds' => ['array'],
            'systeemIds.*' => ['exists:systemen,id'],
        ]);

        $this->wijziging->update([
            'terugvalplan' => $gevalideerd['terugvalplan'] ?: null,
            'impact_toelichting' => $gevalideerd['impactToelichting'] ?: null,
            'externe_referentie' => $gevalideerd['externeReferentie'] ?: null,
        ]);

        // Via de wikkel en niet rauw: een `sync()` laat anders geen spoor na in
        // de audit trail (06b), en de structurele test faalt erop.
        Koppeling::sync($this->wijziging->systemen(), 'geraakte systemen', $gevalideerd['systeemIds']);

        $this->wijziging->refresh();
        session()->flash('melding', 'Dossier bijgewerkt.');
    }

    public function neemInBehandeling(): void
    {
        $this->vereisMuteerbaar();

        $gevalideerd = $this->validate([
            'sjabloonId' => ['required', 'exists:wijzigingssjablonen,id'],
            'geplandOp' => ['required', 'date'],
        ], attributes: ['sjabloonId' => 'sjabloon', 'geplandOp' => 'geplande datum']);

        $sjabloon = Wijzigingssjabloon::with('stappen')->findOrFail($gevalideerd['sjabloonId']);

        try {
            Wijzigingsdossier::neemInBehandeling(
                $this->wijziging,
                $sjabloon,
                now()->parse($gevalideerd['geplandOp']),
            );
        } catch (RuntimeException $e) {
            $this->addError('sjabloonId', $e->getMessage());

            return;
        }

        $this->wijziging->refresh();
        session()->flash('melding', 'Dossier in behandeling genomen; de stappen staan klaar.');
    }

    public function verzetPlanning(): void
    {
        $this->vereisMuteerbaar();

        $gevalideerd = $this->validate(
            ['geplandOp' => ['required', 'date']],
            attributes: ['geplandOp' => 'geplande datum'],
        );

        Wijzigingsdossier::verzetPlanning($this->wijziging, now()->parse($gevalideerd['geplandOp']));

        $this->wijziging->refresh();
        session()->flash('melding', 'Planning verzet; de openstaande stappen zijn meeverschoven.');
    }

    // --- Stappen -----------------------------------------------------------

    /** Zeggenschap over een stap: de eigenaar zelf, of een CISO. */
    private function vereisZeggenschap(Taak $stap): void
    {
        abort_unless($stap->isVanMij() || $this->magMuteren(), 403);
    }

    /** Vers opgehaald, en beperkt tot de reeks van dít dossier. */
    private function stap(int $stapId): Taak
    {
        $stap = $this->stappen()->firstWhere('id', $stapId);

        abort_if($stap === null, 404);

        return $stap;
    }

    /**
     * Een stap aan een persoon toewijzen.
     *
     * Dit hoort bij het dossier en niet bij het sjabloon: wie een stap doet,
     * blijkt pas als de wijziging er is. Zolang een stap geen eigenaar heeft
     * staat hij bij niemand onder "mijn taken" en gaat er geen bericht uit — de
     * reeks loopt dan stil zonder dat iemand iets merkt.
     */
    public function wijsToe(int $stapId, string $gebruikerId): void
    {
        $this->vereisMuteerbaar();

        $stap = $this->stap($stapId);

        // Een voltooide stap opnieuw toewijzen zou de historie herschrijven:
        // de trail zegt dan dat iemand anders hem afrondde.
        abort_if($stap->status === 'voltooid', 422);

        $gevalideerd = validator(
            ['eigenaar' => $gebruikerId ?: null],
            ['eigenaar' => ['nullable', new KiesbareGebruiker($stap->eigenaar_id)]],
        )->validate();

        $stap->update(['eigenaar_id' => $gevalideerd['eigenaar']]);

        // Staat de stap al open, dan hoort de nieuwe eigenaar dat nú te weten;
        // het bericht bij het actueel worden (07b §9) is voor hem al voorbij.
        if ($gevalideerd['eigenaar'] !== null && in_array($stap->status, ['open', 'in_uitvoering', 'verlopen'], true)) {
            NotificatieDispatcher::verzend(
                'stap_actueel',
                new StapActueel($stap->refresh()),
                collect([$stap->eigenaar]),
            );
        }

        session()->flash('melding', $gevalideerd['eigenaar'] === null
            ? 'Toewijzing ingetrokken.'
            : 'Stap toegewezen.');
    }

    public function stapVoltooien(int $stapId): void
    {
        $stap = $this->stap($stapId);
        $this->vereisZeggenschap($stap);

        abort_if($stap->status === 'wachtend' || $stap->vraagt_uitkomst, 422);

        try {
            $stap->update(['status' => 'voltooid', 'voltooid_op' => now()]);
        } catch (StapGeblokkeerd $e) {
            session()->flash('belemmering', $e->getMessage());

            return;
        }

        Wijzigingsdossier::werkStatusBij($this->wijziging);
        $this->wijziging->refresh();
        session()->flash('melding', 'Stap afgerond.');
    }

    public function stapBeslissen(int $stapId, string $uitkomst): void
    {
        $stap = $this->stap($stapId);
        $this->vereisZeggenschap($stap);

        abort_unless($stap->vraagt_uitkomst && $stap->status !== 'wachtend', 422);
        abort_unless(in_array($uitkomst, ['goedgekeurd', 'afgekeurd'], true), 422);

        try {
            Wijzigingsdossier::legUitkomstVast($this->wijziging, $stap, $uitkomst);
        } catch (StapGeblokkeerd $e) {
            session()->flash('belemmering', $e->getMessage());

            return;
        }

        $this->wijziging->refresh();
        session()->flash('melding', $uitkomst === 'goedgekeurd'
            ? 'Stap goedgekeurd.'
            : ($this->wijziging->status === 'afgewezen'
                ? 'Stap afgekeurd; de wijziging is afgewezen.'
                : 'Stap afgekeurd; de reeks is teruggezet.'));
    }

    // --- Afsluiten ---------------------------------------------------------

    public function afsluiten(): void
    {
        $this->vereisMuteerbaar();

        $gevalideerd = $this->validate(
            ['evaluatie' => ['required', 'string']],
            attributes: ['evaluatie' => 'evaluatie'],
        );

        try {
            Wijzigingsdossier::sluit(
                $this->wijziging,
                $this->geslaagd,
                $this->teruggedraaid,
                $gevalideerd['evaluatie'],
            );
        } catch (RuntimeException $e) {
            $this->addError('evaluatie', $e->getMessage());

            return;
        }

        $this->toontAfsluiten = false;
        $this->wijziging->refresh();
        session()->flash('melding', 'Wijziging geëvalueerd en gesloten.');
    }

    /**
     * Correctie op een eindstand (§15). Staat bewust los van
     * `vereisMuteerbaar()`: die weigert juist op een afgerond dossier, en dit is
     * de enige handeling die daar wél mag.
     */
    public function heropenen(): void
    {
        abort_unless($this->magMuteren(), 403);

        try {
            Wijzigingsdossier::heropen($this->wijziging);
        } catch (RuntimeException $e) {
            session()->flash('belemmering', $e->getMessage());

            return;
        }

        $this->wijziging->refresh();
        $this->evaluatie = '';
        $this->geslaagd = true;
        $this->teruggedraaid = false;

        session()->flash('melding', 'Dossier heropend. De evaluatie is vervallen; de oude stand staat in de audit trail.');
    }

    public function annuleren(): void
    {
        $this->vereisMuteerbaar();

        $this->wijziging->update(['status' => 'geannuleerd']);
        session()->flash('melding', 'Wijziging geannuleerd.');
    }

    /** Muteren mag alleen zolang het dossier loopt; een eindstand is read-only. */
    private function vereisMuteerbaar(): void
    {
        abort_unless($this->magMuteren(), 403);
        abort_if($this->wijziging->isAfgerond(), 422);
    }

    public function render()
    {
        $stappen = $this->stappen();

        return view('livewire.wijziging-detail', [
            'stappen' => $stappen,
            'belemmeringVoorSluiten' => Wijzigingsdossier::belemmeringVoorSluiten($this->wijziging),
            // Alle nog lopende stappen die bij niemand op het bord staan.
            'zonderEigenaar' => $stappen
                ->filter(fn (Taak $stap) => $stap->eigenaar_id === null && $stap->status !== 'voltooid')
                ->count(),
            'gebruikers' => Gebruiker::kiesbaar($stappen->pluck('eigenaar_id')->filter()->all()),
            'sjablonen' => Wijzigingssjabloon::where('actief', true)->orderBy('naam')->pluck('naam', 'id'),
            'alleSystemen' => Systeem::inGebruik()->orderBy('naam')->pluck('naam', 'id'),
        ]);
    }
}
