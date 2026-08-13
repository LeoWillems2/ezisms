<?php

namespace App\Livewire;

use App\Models\Systeem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SystemenOverzicht extends Component
{
    /** Afgevoerde systemen blijven als historie bestaan, maar staan standaard uit de weg. */
    #[Url]
    public bool $toonAfgevoerde = false;

    public bool $toontFormulier = false;

    public ?int $bewerktId = null;

    public string $naam = '';

    public string $hostingtype = 'intern';

    public string $beschikbaarheidseis = '';

    /** Drie standen: '' = onbekend, '1' = ja, '0' = nee. */
    public string $redundant = '';

    public string $redundantieToelichting = '';

    private const FORMULIERVELDEN = [
        'bewerktId', 'naam', 'hostingtype', 'beschikbaarheidseis', 'redundant', 'redundantieToelichting',
    ];

    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['asset-classificatie', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['asset-classificatie', 'muteren']);
    }

    public function nieuwSysteem(): void
    {
        $this->vereisMuteren();
        $this->reset(self::FORMULIERVELDEN);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function bewerk(Systeem $systeem): void
    {
        $this->vereisMuteren();
        $this->resetValidation();
        $this->bewerktId = $systeem->id;
        $this->naam = $systeem->naam;
        $this->hostingtype = $systeem->hostingtype;
        $this->beschikbaarheidseis = $systeem->beschikbaarheidseis ?? '';
        $this->redundant = $systeem->redundant === null ? '' : ($systeem->redundant ? '1' : '0');
        $this->redundantieToelichting = $systeem->redundantie_toelichting ?? '';
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
    }

    public function opslaan(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'hostingtype' => ['required', Rule::in(['intern', 'extern'])],
            'beschikbaarheidseis' => ['nullable', Rule::in(Systeem::BESCHIKBAARHEIDSEISEN)],
            'redundant' => [Rule::in(['', '0', '1'])],
            'redundantieToelichting' => ['nullable', 'string', 'max:255'],
        ], attributes: ['naam' => 'naam', 'hostingtype' => 'hostingtype', 'beschikbaarheidseis' => 'beschikbaarheidseis']);

        Systeem::updateOrCreate(['id' => $this->bewerktId], [
            'naam' => $this->naam,
            'hostingtype' => $this->hostingtype,
            'beschikbaarheidseis' => $this->beschikbaarheidseis ?: null,
            // '' -> null (onbekend), anders de bool. Zonder redundantie-eis geen
            // toelichting bewaren.
            'redundant' => $this->redundant === '' ? null : (bool) $this->redundant,
            'redundantie_toelichting' => $this->redundantieToelichting ?: null,
        ]);

        $this->toontFormulier = false;
        session()->flash('melding', $this->bewerktId ? 'Systeem bijgewerkt.' : 'Systeem toegevoegd.');
        $this->reset(self::FORMULIERVELDEN);
    }

    /**
     * Afvoeren i.p.v. verwijderen: het systeem verdwijnt niet uit het register
     * maar krijgt een status, zodat de historie en de assetkoppelingen bewaard
     * blijven en de gebeurtenis in de audit trail komt (implementatie/03 §4).
     */
    public function afvoeren(Systeem $systeem): void
    {
        $this->vereisMuteren();

        $systeem->update(['status' => 'afgevoerd', 'afgevoerd_op' => now()]);
        session()->flash('melding', 'Systeem afgevoerd. Bestaande assetkoppelingen blijven als historie behouden.');
    }

    /** Een per abuis afgevoerd systeem weer in gebruik nemen — ook dit is auditbaar. */
    public function heractiveren(Systeem $systeem): void
    {
        $this->vereisMuteren();

        $systeem->update(['status' => 'in_gebruik', 'afgevoerd_op' => null]);
        session()->flash('melding', 'Systeem opnieuw in gebruik genomen.');
    }

    public function render()
    {
        return view('livewire.systemen-overzicht', [
            'systemen' => Systeem::withCount('assets')
                ->when(! $this->toonAfgevoerde, fn ($q) => $q->inGebruik())
                ->orderBy('naam')
                ->get(),
            // A.8.14-signaal: kritieke systemen (in gebruik) zonder aangetoonde
            // redundantie — ongeacht de toonAfgevoerde-filter.
            'redundantieGaps' => Systeem::inGebruik()->get()->filter->heeftRedundantieGap()->count(),
        ]);
    }
}
