<?php

namespace App\Livewire;

use App\Livewire\Concerns\LevertSchermkopie;
use App\Models\Auditronde;
use App\Models\Bevinding;
use App\Support\Schermkopie;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Het bevindingenregister over alle auditrondes heen (§9.2).
 *
 * Bevindingen leggen we per ronde vast, maar de vraag die je erover stelt is
 * bijna nooit rondegebonden: "welke non-conformiteiten staan er nog open?".
 * Zonder deze pagina was het antwoord alleen te vinden door elke ronde te
 * openen — een open minor uit een afgeronde ronde van drie jaar terug bleef zo
 * onzichtbaar achter een telling op /audits.
 *
 * Read-only. Vastleggen, sluiten en opvolgen blijft in het rondedossier, achter
 * de record-guard van implementatie/11 §4: die onafhankelijkheid mag niet via
 * een overzichtspagina te omzeilen zijn.
 */
#[Layout('components.layouts.app')]
class BevindingenOverzicht extends Component
{
    use LevertSchermkopie;

    /**
     * Pseudo-status voor "alles wat niet gesloten is" — precies het getal dat de
     * badges op /audits tellen. Het is geen kolomwaarde: 'open' en
     * 'non_conformiteit_gestart' staan er allebei onder.
     */
    public const OPENSTAAND = 'openstaand';

    /** @var array<string, string> */
    private const TYPE_LABELS = [
        'non_conformiteit_major' => 'Non conformiteit major',
        'non_conformiteit_minor' => 'Non conformiteit minor',
        'observatie' => 'Observatie',
        'verbeterkans' => 'Verbeterkans',
    ];

    /** @var array<string, string> */
    private const STATUS_LABELS = [
        self::OPENSTAAND => 'Openstaand (niet gesloten)',
        'open' => 'Open',
        'non_conformiteit_gestart' => 'Non-conformiteit gestart',
        'gesloten' => 'Gesloten',
    ];

    #[Url]
    public string $filterType = '';

    /**
     * Standaard alleen wat nog openstaat: dit scherm is er om te vinden wat nog
     * werk vraagt. De regel onder de filters noemt altijd het getoonde aantal
     * naast het totaal, zodat het filter nooit als volledigheid leest.
     */
    #[Url]
    public string $filterStatus = self::OPENSTAAND;

    #[Url]
    public string $filterRonde = '';

    public function mount(): void
    {
        // De filters komen uit de URL en zijn dus vrij in te tikken. Een
        // onbekende waarde zou een lege lijst opleveren die er als "niets
        // gevonden" uitziet; val dan terug op de standaard.
        if ($this->filterType !== '' && ! isset(self::TYPE_LABELS[$this->filterType])) {
            $this->filterType = '';
        }

        if ($this->filterStatus !== '' && ! isset(self::STATUS_LABELS[$this->filterStatus])) {
            $this->filterStatus = self::OPENSTAAND;
        }

        if ($this->filterRonde !== '' && ! Auditronde::whereKey($this->filterRonde)->exists()) {
            $this->filterRonde = '';
        }
    }

    public function render()
    {
        return view('livewire.bevindingen-overzicht', [
            'bevindingen' => $this->gefilterdeBevindingen(),
            'totaalRijen' => Bevinding::count(),
            'typeOpties' => self::TYPE_LABELS,
            'statusOpties' => self::STATUS_LABELS,
            'rondeOpties' => $this->rondeOpties(),
        ]);
    }

    /** @return Collection<int, Bevinding> */
    private function gefilterdeBevindingen(): Collection
    {
        return Bevinding::query()
            ->with(['auditronde.auditplan', 'maatregel', 'afwijking'])
            ->when($this->filterType !== '', fn ($q) => $q->where('type', $this->filterType))
            ->when($this->filterStatus === self::OPENSTAAND,
                fn ($q) => $q->where('status', '!=', 'gesloten'))
            ->when($this->filterStatus !== '' && $this->filterStatus !== self::OPENSTAAND,
                fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterRonde !== '', fn ($q) => $q->where('auditronde_id', (int) $this->filterRonde))
            // Zwaarste eerst: een major die tussen twintig verbeterkansen
            // wegzakt is precies wat dit scherm moet voorkomen. `case` en niet
            // MySQL's FIELD(): de testsuite draait op SQLite.
            ->orderByRaw("case type
                when 'non_conformiteit_major' then 1
                when 'non_conformiteit_minor' then 2
                when 'observatie' then 3
                else 4 end")
            ->orderByDesc('id')
            ->get();
    }

    /**
     * De rondes waar daadwerkelijk bevindingen aan hangen — een lege ronde in
     * het filter levert alleen een keuze op die niets kan opleveren.
     *
     * @return array<int, string>
     */
    private function rondeOpties(): array
    {
        return Auditronde::query()
            ->whereHas('bevindingen')
            ->with('auditplan')
            ->get()
            // Recentste jaarplan bovenaan, daarbinnen de laatst aangemaakte ronde.
            ->sortByDesc(fn (Auditronde $ronde) => ($ronde->auditplan?->jaar ?? 0) * 100000 + $ronde->id)
            ->mapWithKeys(fn (Auditronde $ronde) => [$ronde->id => $ronde->auditOmschrijving()])
            ->all();
    }

    public function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    // --- Kopie voor de auditor (implementatie/12h) -------------------------

    protected function kopieBlok(): string
    {
        return 'auditmanagement';
    }

    protected function schermkopie(): Schermkopie
    {
        $bevindingen = $this->gefilterdeBevindingen();

        return new Schermkopie(
            scherm: 'Bevindingenregister',
            // Dezelfde kolommen als het scherm, plus twee die op het scherm een
            // knop zijn: "Openen" naar de afwijking en de sluitdatum in het
            // rondedossier. In een document wordt een knop niets — en zonder de
            // opvolging is dit een lijst met constateringen, geen bewijs dat er
            // iets mee gebeurd is (§9.2/§10.2).
            kolommen: ['Type', 'Omschrijving', 'Maatregel', 'Auditronde', 'Status', 'Opvolging', 'Gesloten op'],
            rijen: $bevindingen->map(fn (Bevinding $bevinding) => [
                $this->typeLabel($bevinding->type),
                // De volledige tekst, niet de afgekapte versie van het scherm:
                // die afkapping is een kolombreedte, geen inhoudelijke keuze.
                $bevinding->omschrijving,
                $bevinding->maatregel ? 'A.'.$bevinding->maatregel->annex_a_referentie : null,
                $bevinding->auditronde?->auditOmschrijving(),
                $this->statusLabel($bevinding->status),
                self::opvolgingLabel($bevinding),
                $bevinding->gesloten_op?->format('d-m-Y'),
            ])->all(),
            // Het hele register, ook als er gefilterd is — en dit scherm filtert
            // standaard. Juist dát verschil moet de kop noemen (12h §4).
            totaalRijen: Bevinding::count(),
            filters: $this->actieveFilters(),
            eenheid: 'bevindingen',
            toelichting: 'De auditbevindingen uit §9.2, over alle auditrondes heen. '
                .'"Openstaand" betekent hier: niet gesloten — dat is dus zowel een bevinding waar '
                .'nog niets mee gedaan is als een non-conformiteit waarvoor al een afwijking loopt. '
                .'Een non-conformiteit (major/minor) kan pas gesloten worden als de gekoppelde '
                .'afwijking uit §10.2 gesloten is; een observatie of verbeterkans mag direct dicht. '
                .'De kolom "Opvolging" toont die gekoppelde afwijking. De zwaarste bevindingen staan '
                .'bovenaan, daarbinnen de laatst vastgelegde eerst. Vastleggen en sluiten gebeurt in '
                .'het rondedossier; na het afronden van een ronde is de inhoud van een bevinding bevroren.',
            // Geen persoonskolom: dit register beschrijft constateringen, geen
            // personen. De omschrijving is vrije tekst van de auditor — wat
            // daarin staat is een auteurskeuze, geen veld dat het systeem vult.
            metPersoonsgegevens: false,
        );
    }

    /**
     * Waar de opvolging staat: de afwijking die uit de bevinding is voortgekomen.
     * Bewust op `gesloten_op` van de al ingeladen afwijking en niet op
     * `afgeleideStatus()` — die doet per rij twee extra query's, en de vraag die
     * een auditor hier stelt is of de corrigerende actie rond is.
     */
    private static function opvolgingLabel(Bevinding $bevinding): ?string
    {
        $afwijking = $bevinding->afwijking;

        if ($afwijking === null) {
            return null;
        }

        return $afwijking->isGesloten()
            ? 'Afwijking gesloten op '.$afwijking->gesloten_op->format('d-m-Y')
            : 'Afwijking loopt';
    }

    /** @return array<string, string> label => actieve waarde, voor de omvangregel */
    private function actieveFilters(): array
    {
        $filters = [];

        if ($this->filterType !== '') {
            $filters['Type'] = $this->typeLabel($this->filterType);
        }

        if ($this->filterStatus !== '') {
            $filters['Status'] = $this->statusLabel($this->filterStatus);
        }

        if ($this->filterRonde !== '') {
            $filters['Auditronde'] = Auditronde::find($this->filterRonde)?->auditOmschrijving() ?? $this->filterRonde;
        }

        return $filters;
    }
}
