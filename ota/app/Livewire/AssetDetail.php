<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\Classificatieschema;
use App\Models\Gebruiker;
use App\Models\OrganisatieEenheid;
use App\Models\Systeem;
use App\Rules\KiesbareGebruiker;
use App\Support\Koppeling;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AssetDetail extends Component
{
    public Asset $asset;

    // Basisgegevens.
    public string $naam = '';

    public string $type = 'informatie';

    public string $omschrijving = '';

    public string $organisatieEenheidId = '';

    public string $accountableId = '';

    public string $responsibleId = '';

    public bool $binnenScope = true;

    // Classificatie.
    public string $vertrouwelijkheidsniveau = '';

    public string $integriteitsniveau = '';

    public string $beschikbaarheidsniveau = '';

    /** Soort persoonsgegevens; leeg = nog niet beoordeeld (implementatie/03b §3). */
    public string $persoonsgegevens = '';

    /** @var array<int, int> */
    public array $geselecteerdeSystemen = [];

    // Toewijzing-formulier.
    public string $toewijzingGebruikerId = '';

    public ?string $toewijzingDatum = null;

    // Vertrouwelijkheid en integriteit delen de gevoeligheidsschaal;
    // beschikbaarheid loopt gelijk met de A.8.14-eis op een systeem (§2).
    private const GEVOELIGHEIDSNIVEAUS = ['openbaar', 'intern', 'vertrouwelijk', 'geheim'];

    private const BESCHIKBAARHEIDSNIVEAUS = ['niet_kritiek', 'normaal', 'hoog', 'bedrijfskritiek'];

    public function mount(Asset $asset): void
    {
        $this->asset = $asset;
        $this->laadVelden();
        $this->toewijzingDatum = now()->format('Y-m-d');
    }

    private function laadVelden(): void
    {
        $this->naam = $this->asset->naam;
        $this->type = $this->asset->type;
        $this->omschrijving = $this->asset->omschrijving ?? '';
        $this->organisatieEenheidId = (string) $this->asset->organisatie_eenheid_id;
        $this->accountableId = (string) $this->asset->accountable_id;
        $this->responsibleId = (string) $this->asset->responsible_id;
        $this->binnenScope = $this->asset->binnen_scope;
        $this->vertrouwelijkheidsniveau = (string) $this->asset->vertrouwelijkheidsniveau;
        $this->integriteitsniveau = (string) $this->asset->integriteitsniveau;
        $this->beschikbaarheidsniveau = (string) $this->asset->beschikbaarheidsniveau;
        $this->persoonsgegevens = (string) $this->asset->persoonsgegevens;
        $this->geselecteerdeSystemen = $this->asset->systemen->pluck('id')->all();
    }

    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['asset-classificatie', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['asset-classificatie', 'muteren']);
    }

    public function opslaanBasis(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['informatie', 'systeem_of_dienst', 'hardware'])],
            'omschrijving' => ['nullable', 'string'],
            'organisatieEenheidId' => ['required', Rule::exists('organisatie_eenheden', 'id')],
            'accountableId' => ['nullable', new KiesbareGebruiker($this->asset->accountable_id)],
            'responsibleId' => ['nullable', new KiesbareGebruiker($this->asset->responsible_id)],
            'binnenScope' => ['boolean'],
        ], attributes: [
            'naam' => 'naam',
            'organisatieEenheidId' => 'organisatie-eenheid',
        ]);

        $this->asset->update([
            'naam' => $gevalideerd['naam'],
            'type' => $gevalideerd['type'],
            'omschrijving' => $gevalideerd['omschrijving'] ?: null,
            'organisatie_eenheid_id' => (int) $gevalideerd['organisatieEenheidId'],
            // Lege string = bewust geen eigenaar; naar null, niet naar 0.
            'accountable_id' => $gevalideerd['accountableId'] !== '' ? (int) $gevalideerd['accountableId'] : null,
            'responsible_id' => $gevalideerd['responsibleId'] !== '' ? (int) $gevalideerd['responsibleId'] : null,
            'binnen_scope' => $gevalideerd['binnenScope'],
        ]);

        session()->flash('melding', 'Basisgegevens opgeslagen.');
    }

    public function opslaanClassificatie(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'vertrouwelijkheidsniveau' => ['nullable', Rule::in(self::GEVOELIGHEIDSNIVEAUS)],
            'integriteitsniveau' => ['nullable', Rule::in(self::GEVOELIGHEIDSNIVEAUS)],
            'beschikbaarheidsniveau' => ['nullable', Rule::in(self::BESCHIKBAARHEIDSNIVEAUS)],
            'persoonsgegevens' => ['nullable', Rule::in(Asset::PERSOONSGEGEVENSSOORTEN)],
        ]);

        // De observer tilt de status op 'actief' zodra alle drie ingevuld zijn.
        // `persoonsgegevens` telt daar niet in mee — zie Asset::isGeclassificeerd().
        $this->asset->update([
            // '' betekent "niet geclassificeerd" en hoort als null in de kolom,
            // anders faalt de enum-constraint.
            'vertrouwelijkheidsniveau' => $this->vertrouwelijkheidsniveau ?: null,
            'integriteitsniveau' => $this->integriteitsniveau ?: null,
            'beschikbaarheidsniveau' => $this->beschikbaarheidsniveau ?: null,
            'persoonsgegevens' => $this->persoonsgegevens ?: null,
            // Alleen een ingevulde beoordeling krijgt een datum; leegmaken zet
            // hem terug op "nog niet beoordeeld", inclusief de datum.
            'privacy_beoordeeld_op' => $this->persoonsgegevens ? now() : null,
        ]);

        $this->asset->refresh();
        session()->flash('melding', 'Classificatie opgeslagen.');
    }

    public function systemenOpslaan(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'geselecteerdeSystemen' => ['array'],
            'geselecteerdeSystemen.*' => ['integer', 'exists:systemen,id'],
        ]);

        Koppeling::sync($this->asset->systemen(), 'systemen', $this->geselecteerdeSystemen);
        session()->flash('melding', 'Gekoppelde systemen opgeslagen.');
    }

    public function toewijzen(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'toewijzingGebruikerId' => ['required', new KiesbareGebruiker],
            'toewijzingDatum' => ['required', 'date'],
        ], attributes: [
            'toewijzingGebruikerId' => 'gebruiker',
            'toewijzingDatum' => 'datum',
        ]);

        $this->asset->toewijzingen()->create([
            'gebruiker_id' => (int) $gevalideerd['toewijzingGebruikerId'],
            'toegewezen_op' => $gevalideerd['toewijzingDatum'],
        ]);

        $this->reset('toewijzingGebruikerId');
        $this->toewijzingDatum = now()->format('Y-m-d');
        session()->flash('melding', 'Asset toegewezen.');
    }

    public function retourRegistreren(int $toewijzingId): void
    {
        $this->vereisMuteren();

        // updateGeaudit: de retourregistratie is het bewijs voor Annex A 5.11,
        // en een massa-update zou hem buiten de audit trail houden.
        $this->asset->toewijzingen()
            ->whereKey($toewijzingId)
            ->whereNull('geretourneerd_op')
            ->updateGeaudit(['geretourneerd_op' => now()]);

        session()->flash('melding', 'Retour geregistreerd.');
    }

    public function buitenGebruikStellen(): void
    {
        $this->vereisMuteren();

        abort_unless(in_array($this->asset->status, ['geregistreerd', 'actief'], true), 422);

        $this->asset->update(['status' => 'buiten_gebruik']);
        session()->flash('melding', 'Asset op "buiten gebruik" gezet.');
    }

    public function afstoten(): void
    {
        $this->vereisMuteren();

        // Annex A 5.11 (return of assets): afstoten mag niet zolang er nog
        // bedrijfsmiddelen bij iemand uitstaan.
        if ($this->asset->heeftOpenToewijzingen()) {
            session()->flash('fout', 'Afstoten kan niet: er zijn nog openstaande toewijzingen. Registreer eerst de retour.');

            return;
        }

        if ($this->asset->status === 'afgestoten') {
            return;
        }

        $this->asset->update(['status' => 'afgestoten']);
        session()->flash('melding', 'Asset afgestoten.');
    }

    public function render()
    {
        // Omgangsregels per (dimensie, niveau), zodat het formulier direct de
        // regels bij het gekozen niveau kan tonen.
        $schemas = Classificatieschema::all()
            ->keyBy(fn ($rij) => $rij->dimensie.':'.$rij->niveau);

        return view('livewire.asset-detail', [
            'schemas' => $schemas,
            // Per dimensie de keuzelijst (waarde => label); beschikbaarheid heeft
            // een eigen schaal (§2).
            'niveauOpties' => [
                'vertrouwelijkheid' => ['openbaar' => 'Openbaar', 'intern' => 'Intern', 'vertrouwelijk' => 'Vertrouwelijk', 'geheim' => 'Geheim'],
                'integriteit' => ['openbaar' => 'Openbaar', 'intern' => 'Intern', 'vertrouwelijk' => 'Vertrouwelijk', 'geheim' => 'Geheim'],
                'beschikbaarheid' => ['niet_kritiek' => 'Niet kritiek', 'normaal' => 'Normaal', 'hoog' => 'Hoog', 'bedrijfskritiek' => 'Bedrijfskritiek'],
            ],
            // Los van $niveauOpties: dit is geen classificatiedimensie maar een
            // eigen veld met een eigen, wettelijk vocabulaire (implementatie/03b §0).
            'persoonsgegevensOpties' => [
                'geen' => 'Geen persoonsgegevens',
                'gewoon' => 'Gewone persoonsgegevens',
                'bijzonder' => 'Bijzondere persoonsgegevens (art. 9 AVG)',
                'strafrechtelijk' => 'Strafrechtelijke gegevens (art. 10 AVG)',
            ],
            'persoonsgegevensUitleg' => [
                'geen' => 'Beoordeeld: dit asset bevat geen persoonsgegevens.',
                'gewoon' => 'Gegevens over een identificeerbare persoon: naam, e-mail, personeelsnummer, IP-adres.',
                'bijzonder' => 'Onder meer gezondheid, ras, geloof, politieke opvatting, seksuele gerichtheid, biometrie en vakbondslidmaatschap.',
                'strafrechtelijk' => 'Gegevens over strafrechtelijke veroordelingen en strafbare feiten.',
            ],
            'eenheden' => OrganisatieEenheid::orderBy('naam')->get(),
            // Elk veld zijn eigen lijst: een gedeactiveerde huidige accountable
            // mag alleen in de accountable-select terugkomen, niet ook in de
            // responsible-select (en omgekeerd). De toewijzing-select is een
            // nieuwe keuze en toont dus alleen actieve accounts.
            'accountableGebruikers' => Gebruiker::kiesbaar($this->accountableId)->pluck('naam', 'id')->all(),
            'responsibleGebruikers' => Gebruiker::kiesbaar($this->responsibleId)->pluck('naam', 'id')->all(),
            'gebruikers' => Gebruiker::kiesbaar()->pluck('naam', 'id')->all(),
            // De opgeslagen RACI-houders, om te signaleren als ze niet meer actief zijn.
            'accountableGebruiker' => $this->asset->accountable,
            'responsibleGebruiker' => $this->asset->responsible,
            // Alleen in-gebruik systemen zijn nieuw koppelbaar, plus de al aan
            // deze asset gekoppelde (ook als die inmiddels is afgevoerd): zo valt
            // een bestaande koppeling niet stilzwijgend weg — zelfde patroon als
            // Gebruiker::kiesbaar() bij een gedeactiveerde eigenaar.
            'systemen' => Systeem::query()
                ->where(fn ($q) => $q->inGebruik()
                    ->orWhereIn('id', $this->geselecteerdeSystemen))
                ->orderBy('naam')
                ->get(),
            'toewijzingen' => $this->asset->toewijzingen()->with('gebruiker')->orderByDesc('toegewezen_op')->get(),
        ]);
    }
}
