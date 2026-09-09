<?php

namespace App\Livewire;

use App\Models\OrganisatieEenheid;
use App\Models\Organisatieprofiel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * De pagina `/organisatie`: de gegevens van de organisatie zelf, en daaronder de
 * eenheden waaruit zij bestaat.
 *
 * Twee onderwerpen op één scherm, want het is één vraag — "voor wie voeren we
 * dit ISMS?" — die op twee detailniveaus wordt beantwoord. Tot 09-09-2026 heette
 * dit adres `/organisatie-eenheden` en stond alleen de boom erop.
 */
#[Layout('components.layouts.app')]
class OrganisatieOverzicht extends Component
{
    /** Wat het scherm als bovengrens aanhoudt; de kolom zelf is een `text`. */
    public const MAX_GEGEVENS = 2000;

    public bool $toontFormulier = false;

    public bool $toontGegevensFormulier = false;

    public string $gegevens = '';

    public string $naam = '';

    public string $type = 'afdeling';

    public ?int $bovenliggendeEenheidId = null;

    /**
     * Herhaalt de check ondanks de route-middleware: de pagina is bereikbaar met
     * 'lezen', maar muteren mag alleen met 'muteren' (conventies §4).
     */
    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['context-scope', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['context-scope', 'muteren']);
    }

    /** Voor het scherm: het maximum als tekenlimiet én als bijschrift. */
    public function maxGegevens(): int
    {
        return self::MAX_GEGEVENS;
    }

    public function bewerkGegevens(): void
    {
        $this->vereisMuteren();
        $this->resetValidation();
        $this->gegevens = (string) Organisatieprofiel::huidig()->gegevens;
        $this->toontGegevensFormulier = true;
    }

    public function sluitGegevensFormulier(): void
    {
        $this->toontGegevensFormulier = false;
    }

    /**
     * `firstOrNew` en niet `create`: er hoort precies één rij te bestaan, dus
     * tweemaal opslaan mag geen tweede profiel opleveren.
     */
    public function gegevensOpslaan(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'gegevens' => ['nullable', 'string', 'max:'.self::MAX_GEGEVENS],
        ], attributes: [
            'gegevens' => 'organisatiegegevens',
        ]);

        $profiel = Organisatieprofiel::query()->firstOrNew();
        // Leeg invullen wist het blok in plaats van een lege string te bewaren:
        // het scherm toont dan weer de regel dat er niets is vastgelegd.
        $profiel->gegevens = trim($this->gegevens) === '' ? null : $this->gegevens;
        $profiel->save();

        $this->toontGegevensFormulier = false;
        session()->flash('melding', 'Organisatiegegevens opgeslagen.');
    }

    public function nieuweEenheid(?int $bovenliggendeId = null): void
    {
        $this->vereisMuteren();
        $this->reset(['naam', 'type', 'bovenliggendeEenheidId']);
        $this->resetValidation();
        $this->bovenliggendeEenheidId = $bovenliggendeId;
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
            'type' => ['required', Rule::in(['afdeling', 'locatie', 'proces'])],
            'bovenliggendeEenheidId' => ['nullable', Rule::exists('organisatie_eenheden', 'id')],
        ], attributes: [
            'naam' => 'naam',
            'type' => 'type',
            'bovenliggendeEenheidId' => 'bovenliggende eenheid',
        ]);

        OrganisatieEenheid::create([
            'naam' => $this->naam,
            'type' => $this->type,
            'bovenliggende_eenheid_id' => $this->bovenliggendeEenheidId,
        ]);

        $this->toontFormulier = false;
        $this->reset(['naam', 'type', 'bovenliggendeEenheidId']);
        session()->flash('melding', 'Organisatie-eenheid toegevoegd.');
    }

    public function verwijderen(OrganisatieEenheid $eenheid): void
    {
        $this->vereisMuteren();

        // nullOnDelete op de zelfverwijzing tilt sub-eenheden naar de wortel op
        // in plaats van ze mee te verwijderen — bewust, om geen data stil te
        // verliezen.
        $eenheid->delete();
        session()->flash('melding', "Eenheid '{$eenheid->naam}' is verwijderd.");
    }

    public function render()
    {
        return view('livewire.organisatie-overzicht', [
            'profiel' => Organisatieprofiel::huidig(),
            // Alleen de wortels ophalen; de Blade-partial rendert de boom recursief.
            'wortels' => OrganisatieEenheid::with('subEenheden')
                ->whereNull('bovenliggende_eenheid_id')
                ->orderBy('naam')
                ->get(),
        ]);
    }
}
