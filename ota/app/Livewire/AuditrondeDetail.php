<?php

namespace App\Livewire;

use App\Livewire\Concerns\LevertSchermkopie;
use App\Models\Afwijking;
use App\Models\Auditobject;
use App\Models\Auditronde;
use App\Models\Bevinding;
use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Models\OrganisatieEenheid;
use App\Support\Koppeling;
use App\Support\Schermkopie;
use App\Support\Schermkopiebijlage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Het rondedossier: planning (scope, uitvoerder, status) en de bevindingen.
 * De scheiding tussen `magPlannen`/`magUitvoeren` (administratie/status) en
 * `magBevindingBewerken` (de record-guard uit implementatie/11 §4) is hier de kern.
 */
#[Layout('components.layouts.app')]
class AuditrondeDetail extends Component
{
    use LevertSchermkopie;

    private const TYPES = ['intern', 'intern_nulmeting', 'extern_certificering', 'extern_surveillance'];

    private const BEVINDING_TYPES = [
        'non_conformiteit_major', 'non_conformiteit_minor', 'observatie', 'verbeterkans',
    ];

    /**
     * De keuze "geen gesprek — eigen waarneming" in de gesprekspartnerlijst. Een
     * auditor die iets in de logbestanden ziet heeft geen gesprekspartner, en
     * moet dat kunnen zeggen in plaats van een naam te verzinnen (11d §12).
     */
    public const EIGEN_WAARNEMING = 'eigen_waarneming';

    /** De afhandelingen die de auditor zelf zet; 'bevinding' is afgeleid (11d §0). */
    private const HANDMATIGE_AFHANDELINGEN = ['geen_opmerkingen', 'niet_toegekomen'];

    /** @var array<string, string> ook 'bevinding', de afgeleide status */
    public const AFHANDELING_LABELS = [
        'niet_behandeld' => 'nog niet behandeld',
        'geen_opmerkingen' => 'geen opmerkingen',
        'bevinding' => 'bevinding',
        'niet_toegekomen' => 'niet aan toegekomen',
    ];

    public Auditronde $auditronde;

    // Planning (administratief, alleen zolang 'gepland').
    public string $type = 'intern';

    public ?string $geplandOp = null;

    public ?int $auditorGebruikerId = null;

    public string $externAuditorNaam = '';

    /** @var array<int, int> geselecteerde organisatie-eenheden (organisatorische scope) */
    public array $scopeEenheden = [];

    /** @var array<int, int> geselecteerde auditobjecten (normatieve scope, plan 11b) */
    public array $scopeObjecten = [];

    // Bevinding-formulier.
    public bool $toontBevindingFormulier = false;

    public ?int $bewerktBevindingId = null;

    public string $bevindingType = 'observatie';

    public string $bevindingOmschrijving = '';

    public string $bevindingAuditobjectId = '';

    public string $bevindingBron = '';

    // Behandeling van één object in de normatieve scope (11d §5).
    public bool $toontBehandelFormulier = false;

    public ?int $behandeldObjectId = null;

    public string $behandelAfhandeling = 'geen_opmerkingen';

    public string $behandelBron = '';

    public string $behandelToelichting = '';

    // Sluiten van een bevinding: de afhandelingsnotitie.
    public bool $toontSluitFormulier = false;

    public ?int $teSluitenBevindingId = null;

    public string $sluitNotitie = '';

    // Afronden met objecten die nog grijs staan: reden per object.
    public bool $toontAfrondFormulier = false;

    /** @var array<int, string> object-id => reden */
    public array $afrondRedenen = [];

    public function mount(Auditronde $auditronde): void
    {
        $this->auditronde = $auditronde->load([
            'auditplan', 'auditor', 'organisatieEenheden', 'auditobjecten', 'bevindingen',
        ]);
        $this->type = $auditronde->type;
        $this->geplandOp = $auditronde->gepland_op?->format('Y-m-d');
        $this->auditorGebruikerId = $auditronde->auditor_gebruiker_id;
        $this->externAuditorNaam = $auditronde->extern_auditor_naam ?? '';
        $this->scopeEenheden = $auditronde->organisatieEenheden->pluck('id')->all();
        $this->scopeObjecten = $auditronde->auditobjecten->pluck('id')->all();
    }

    // --- Autorisatie -------------------------------------------------------

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['auditmanagement', 'muteren']);
    }

    /** Planning is bewerkbaar zolang de ronde nog niet is gestart. */
    public function magPlannen(): bool
    {
        return $this->magMuteren() && $this->auditronde->status === 'gepland';
    }

    public function magUitvoeren(): bool
    {
        return $this->auditronde->magUitvoerenDoor(auth()->user());
    }

    public function magBevindingBewerken(): bool
    {
        return $this->auditronde->magBevindingBewerkenDoor(auth()->user());
    }

    private function vereisPlannen(): void
    {
        abort_unless($this->magPlannen(), 403);
    }

    private function vereisUitvoeren(): void
    {
        abort_unless($this->magUitvoeren(), 403);
    }

    private function vereisBevindingBewerken(): void
    {
        abort_unless($this->magBevindingBewerken(), 403);
    }

    /** Opvolging (non-conformiteit starten, sluiten) is CISO-muteren (§4b). */
    private function vereisOpvolgen(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    // --- Planning ----------------------------------------------------------

    public function slaPlanningOp(): void
    {
        $this->vereisPlannen();

        $gevalideerd = $this->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'geplandOp' => ['nullable', 'date'],
            'auditorGebruikerId' => ['nullable', Rule::exists('gebruikers', 'id')],
            'externAuditorNaam' => ['nullable', 'string', 'max:255'],
            'scopeEenheden' => ['array'],
            'scopeEenheden.*' => [Rule::exists('organisatie_eenheden', 'id')],
            'scopeObjecten' => ['array'],
            'scopeObjecten.*' => [Rule::exists('auditobjecten', 'id')],
        ], attributes: [
            'type' => 'type',
            'geplandOp' => 'geplande datum',
            'auditorGebruikerId' => 'auditor',
            'externAuditorNaam' => 'externe auditor',
        ]);

        $intern = in_array($gevalideerd['type'], Auditronde::INTERNE_TYPEN, true);

        $this->auditronde->update([
            'type' => $gevalideerd['type'],
            'gepland_op' => $gevalideerd['geplandOp'] ?: null,
            // Elk type houdt alleen zijn eigen uitvoerdersveld; het andere wordt
            // leeggemaakt zodat er geen verweesde toewijzing blijft staan.
            'auditor_gebruiker_id' => $intern ? ($gevalideerd['auditorGebruikerId'] ?: null) : null,
            'extern_auditor_naam' => $intern ? null : ($gevalideerd['externAuditorNaam'] ?: null),
        ]);

        Koppeling::sync($this->auditronde->organisatieEenheden(), 'organisatie-eenheden', $this->scopeEenheden);
        Koppeling::sync($this->auditronde->auditobjecten(), 'auditobjecten', $this->scopeObjecten);
        $this->auditronde->refresh()->load(['auditplan', 'auditor', 'organisatieEenheden', 'auditobjecten']);

        session()->flash('melding', 'Planning bijgewerkt.');
    }

    /**
     * De dekkingsvlag omzetten (plan 11c fase 1). Bewust los van `magPlannen()`:
     * of een ronde meetelt is een planningsbeslissing die ook ná afronding nog
     * juist moet kunnen worden gezet — je ziet vaak pas achteraf dat een
     * her-audit de matrix zou vertekenen.
     *
     * Bewust `magMuteren()` en niet de record-guard: een auditor mag zijn eigen
     * ronde niet uit de dekkingsmatrix schrijven (plan 11c §9).
     */
    public function wisselDekkingsvlag(): void
    {
        abort_unless($this->magMuteren(), 403);

        $nieuw = ! $this->auditronde->telt_mee_voor_dekking;
        $this->auditronde->update(['telt_mee_voor_dekking' => $nieuw]);
        $this->auditronde->refresh();

        session()->flash('melding', $nieuw
            ? 'Ronde telt weer mee voor de dekkingsmatrix.'
            : 'Ronde telt niet mee voor de dekkingsmatrix; hij blijft wel volledig in het dossier.');
    }

    public function startUitvoering(): void
    {
        $this->vereisUitvoeren();
        if ($this->auditronde->status === 'gepland') {
            $this->auditronde->update(['status' => 'in_uitvoering']);
        }
    }

    /**
     * Afronden bevriest de bevindingen én de behandelingen (§4a/§5) —
     * eenrichtingsverkeer. Staat er nog iets grijs in de normatieve scope, dan
     * vraagt het scherm daar eerst een reden voor (11d §5): "niet aan
     * toegekomen" is een reëel auditresultaat, en wie het moet wegpoetsen om te
     * kunnen afronden vult "geen opmerkingen" in over iets dat hij nooit heeft
     * bekeken.
     */
    public function rondAf(): void
    {
        $this->vereisUitvoeren();

        if ($this->auditronde->status !== 'in_uitvoering') {
            return;
        }

        $onbehandeld = $this->auditronde->onbehandeldeObjecten();

        if ($onbehandeld->isNotEmpty()) {
            $this->afrondRedenen = $onbehandeld->mapWithKeys(fn (Auditobject $o) => [$o->id => ''])->all();
            $this->resetValidation();
            $this->toontAfrondFormulier = true;

            return;
        }

        $this->voerAfrondingUit();
    }

    /** De redenen vastleggen en dan pas afronden. */
    public function rondAfMetRedenen(): void
    {
        $this->vereisUitvoeren();
        $this->vereisBevindingBewerken();

        $this->validate(
            ['afrondRedenen.*' => ['required', 'string', 'max:255']],
            ['afrondRedenen.*.required' => 'Geef aan waarom dit object niet is behandeld.'],
        );

        $statussen = $this->auditronde->objectstatussen();

        foreach ($this->afrondRedenen as $objectId => $reden) {
            // Tussentijds alsnog behandeld: dan is de reden niet meer waar.
            if (($statussen[$objectId] ?? null) !== 'niet_behandeld') {
                continue;
            }

            $this->schrijfAfhandeling((int) $objectId, 'niet_toegekomen', null, $reden);
        }

        $this->toontAfrondFormulier = false;
        $this->afrondRedenen = [];
        $this->voerAfrondingUit();
    }

    private function voerAfrondingUit(): void
    {
        $this->auditronde->update([
            'status' => 'afgerond',
            'uitgevoerd_op' => $this->auditronde->uitgevoerd_op ?? now()->toDateString(),
        ]);
    }

    // --- Behandeling per auditobject (11d) ---------------------------------

    public function behandelObject(int $objectId): void
    {
        $this->vereisBevindingBewerken();

        $object = $this->auditronde->auditobjecten->firstWhere('id', $objectId);
        abort_if($object === null, 404);

        // Een object met een bevinding is niet handmatig te zetten: die status is
        // afgeleid, en twee vastleggingen van hetzelfde feit kunnen elkaar
        // tegenspreken (11d §0).
        if (($this->auditronde->objectstatussen()[$objectId] ?? null) === 'bevinding') {
            return;
        }

        $this->behandeldObjectId = $objectId;
        $this->behandelAfhandeling = $object->pivot->afhandeling === 'niet_behandeld'
            ? 'geen_opmerkingen'
            : $object->pivot->afhandeling;
        $this->behandelBron = $object->pivot->eigen_waarneming
            ? self::EIGEN_WAARNEMING
            : (string) $object->pivot->gesproken_met_id;
        $this->behandelToelichting = (string) $object->pivot->toelichting;
        $this->resetValidation();
        $this->toontBehandelFormulier = true;
    }

    public function sluitBehandelFormulier(): void
    {
        $this->toontBehandelFormulier = false;
    }

    public function slaBehandelingOp(): void
    {
        $this->vereisBevindingBewerken();

        $gevalideerd = $this->validate([
            'behandeldObjectId' => ['required', Rule::exists('auditobjecten', 'id')],
            'behandelAfhandeling' => ['required', Rule::in(self::HANDMATIGE_AFHANDELINGEN)],
            // Zonder bron is groen een bewering en geen bewijs (11d §0); een gat
            // zonder reden is precies wat de externe auditor niet kan plaatsen.
            // De bron mag wél "eigen waarneming" zijn: ook een nagelezen
            // procedure is een bron (11d §12).
            'behandelBron' => [
                Rule::requiredIf($this->behandelAfhandeling === 'geen_opmerkingen'),
                'nullable',
                Rule::when(
                    $this->behandelBron !== self::EIGEN_WAARNEMING,
                    [Rule::exists('gebruikers', 'id')],
                ),
            ],
            'behandelToelichting' => [
                Rule::requiredIf($this->behandelAfhandeling === 'niet_toegekomen'),
                'nullable', 'string', 'max:255',
            ],
        ], attributes: [
            'behandelBron' => 'bron',
            'behandelToelichting' => 'reden',
        ]);

        $geenOpmerkingen = $gevalideerd['behandelAfhandeling'] === 'geen_opmerkingen';
        $eigenWaarneming = $geenOpmerkingen
            && $gevalideerd['behandelBron'] === self::EIGEN_WAARNEMING;

        $this->schrijfAfhandeling(
            (int) $gevalideerd['behandeldObjectId'],
            $gevalideerd['behandelAfhandeling'],
            $geenOpmerkingen && ! $eigenWaarneming ? (int) $gevalideerd['behandelBron'] : null,
            $geenOpmerkingen ? null : $gevalideerd['behandelToelichting'],
            $eigenWaarneming,
        );

        $this->toontBehandelFormulier = false;
        session()->flash('melding', 'Behandeling vastgelegd.');
    }

    /**
     * De pivotwijziging plus de audit trail (11d §7). De regel is een zin en geen
     * kolomwaarden: "gesproken_met_id: 4" zegt een auditor niets.
     */
    private function schrijfAfhandeling(
        int $objectId,
        string $afhandeling,
        ?int $gesprokenMetId,
        ?string $toelichting,
        bool $eigenWaarneming = false,
    ): void {
        $object = $this->auditronde->auditobjecten->firstWhere('id', $objectId);

        Koppeling::werkPivotBij(
            $this->auditronde->auditobjecten(),
            'auditobject',
            $objectId,
            [
                'afhandeling' => $afhandeling,
                'gesproken_met_id' => $gesprokenMetId,
                'eigen_waarneming' => $eigenWaarneming,
                'toelichting' => $toelichting,
            ],
            oud: $object === null ? null : self::afhandelingszin(
                $object->pivot->afhandeling,
                $object->pivot->gesproken_met_id,
                $object->pivot->toelichting,
                (bool) $object->pivot->eigen_waarneming,
            ),
            nieuw: self::afhandelingszin($afhandeling, $gesprokenMetId, $toelichting, $eigenWaarneming),
        );

        $this->auditronde->load('auditobjecten');
    }

    /** "geen opmerkingen (gesproken met Jansen)" — leesbaar in de trail en het scherm. */
    private static function afhandelingszin(
        string $afhandeling,
        ?int $gesprokenMetId,
        ?string $toelichting,
        bool $eigenWaarneming = false,
    ): string {
        $zin = self::AFHANDELING_LABELS[$afhandeling] ?? $afhandeling;

        if ($eigenWaarneming) {
            return $zin.' (eigen waarneming)';
        }

        if ($gesprokenMetId !== null && ($naam = Gebruiker::find($gesprokenMetId)?->naam) !== null) {
            return $zin.' (gesproken met '.$naam.')';
        }

        return filled($toelichting) ? $zin.' ('.$toelichting.')' : $zin;
    }

    // --- Bevindingen -------------------------------------------------------

    public function nieuweBevinding(): void
    {
        $this->vereisBevindingBewerken();
        $this->reset([
            'bewerktBevindingId', 'bevindingType', 'bevindingOmschrijving',
            'bevindingAuditobjectId', 'bevindingBron',
        ]);
        $this->bevindingType = 'observatie';
        $this->resetValidation();
        $this->toontBevindingFormulier = true;
    }

    public function bewerkBevinding(int $bevindingId): void
    {
        $this->vereisBevindingBewerken();
        $bevinding = $this->auditronde->bevindingen()->findOrFail($bevindingId);
        $this->bewerktBevindingId = $bevinding->id;
        $this->bevindingType = $bevinding->type;
        $this->bevindingOmschrijving = $bevinding->omschrijving;
        $this->bevindingAuditobjectId = (string) $bevinding->auditobject_id;
        $this->bevindingBron = $bevinding->eigen_waarneming
            ? self::EIGEN_WAARNEMING
            : (string) $bevinding->gesproken_met_id;
        $this->resetValidation();
        $this->toontBevindingFormulier = true;
    }

    /**
     * Zodra het onderwerp gekozen is: staat er een gesprekspartner op dat object
     * in de scope, stel die dan voor. Bij leestijd terugvallen had weinig zin —
     * een object met een bevinding draagt zelden een bron — maar als voorstel bij
     * het invullen werkt het wel (11d §12).
     */
    public function updatedBevindingAuditobjectId(string $waarde): void
    {
        if ($this->bevindingBron !== '') {
            return;
        }

        $bron = $this->auditronde->auditobjecten->firstWhere('id', (int) $waarde)?->pivot->gesproken_met_id;

        if ($bron !== null) {
            $this->bevindingBron = (string) $bron;
        }
    }

    public function sluitBevindingFormulier(): void
    {
        $this->toontBevindingFormulier = false;
    }

    public function slaBevindingOp(): void
    {
        $this->vereisBevindingBewerken();

        $gevalideerd = $this->validate([
            'bevindingType' => ['required', Rule::in(self::BEVINDING_TYPES)],
            'bevindingOmschrijving' => ['required', 'string'],
            // Verplicht, en over álle actieve objecten: wie tijdens een interview
            // over iets buiten de scope struikelt moet het kwijt kunnen, en de
            // scope verruimen kan hij niet (11d §0).
            'bevindingAuditobjectId' => [
                'required', Rule::exists('auditobjecten', 'id')->where('actief', true),
            ],
            // Verplicht: een bevinding zonder bron is niet na te lopen. De
            // uitweg is een expliciete keuze, geen leeg veld.
            'bevindingBron' => [
                'required',
                Rule::when(
                    $this->bevindingBron !== self::EIGEN_WAARNEMING,
                    [Rule::exists('gebruikers', 'id')],
                ),
            ],
        ], attributes: [
            'bevindingType' => 'type',
            'bevindingOmschrijving' => 'omschrijving',
            'bevindingAuditobjectId' => 'onderwerp',
            'bevindingBron' => 'bron',
        ]);

        $objectId = (int) $gevalideerd['bevindingAuditobjectId'];
        $eigenWaarneming = $gevalideerd['bevindingBron'] === self::EIGEN_WAARNEMING;

        $attributen = [
            'type' => $gevalideerd['bevindingType'],
            'omschrijving' => $gevalideerd['bevindingOmschrijving'],
            'auditobject_id' => $objectId,
            'gesproken_met_id' => $eigenWaarneming ? null : (int) $gevalideerd['bevindingBron'],
            'eigen_waarneming' => $eigenWaarneming,
        ];

        if ($this->bewerktBevindingId !== null) {
            // Alleen de inhoud; status/opvolging blijft ongemoeid.
            $this->auditronde->bevindingen()->findOrFail($this->bewerktBevindingId)->update($attributen);
        } else {
            $this->auditronde->bevindingen()->create($attributen);
        }

        $this->laatScopeMeegroeien($objectId);

        $this->auditronde->load('bevindingen');
        $this->toontBevindingFormulier = false;
        $this->reset(['bewerktBevindingId', 'bevindingOmschrijving', 'bevindingAuditobjectId', 'bevindingBron']);
        session()->flash('melding', 'Bevinding opgeslagen.');
    }

    /**
     * Een bevinding op een object buiten de normatieve scope trekt dat object de
     * scope in: het is aangeraakt, dus het hoort in het bewijsbeeld. Wel
     * gemarkeerd als bijgroei, want de auditor rekt hiermee zijn eigen ronde op
     * en dat moet de CISO bij de review kunnen zien (11d §0).
     */
    private function laatScopeMeegroeien(int $objectId): void
    {
        if ($this->auditronde->auditobjecten->contains('id', $objectId)) {
            return;
        }

        Koppeling::koppelErbij(
            $this->auditronde->auditobjecten(),
            'auditobjecten',
            [$objectId => ['buiten_planning' => true]],
        );

        $this->auditronde->load('auditobjecten');
    }

    // --- Opvolging (blok 8) ------------------------------------------------

    public function opvolgenAlsNonConformiteit(int $bevindingId): void
    {
        $this->vereisOpvolgen();
        $bevinding = $this->auditronde->bevindingen()->with('afwijking')->findOrFail($bevindingId);

        // Alleen zinnig voor een non-conformiteit die er nog geen heeft.
        if (! $bevinding->isNonConformiteit() || $bevinding->afwijking !== null) {
            return;
        }

        $afwijking = Afwijking::create([
            'bron' => 'audit_bevinding',
            'bevinding_id' => $bevinding->id,
            'omschrijving' => $bevinding->omschrijving,
        ]);

        $bevinding->update(['status' => 'non_conformiteit_gestart']);

        $this->redirectRoute('afwijkingen.detail', $afwijking, navigate: true);
    }

    /**
     * Sluiten vraagt eerst waaróm (11d §13). De belemmeringscheck staat vóór het
     * formulier: eerst een notitie laten typen en daarna alsnog weigeren is de
     * verkeerde volgorde.
     */
    public function sluitBevinding(int $bevindingId): void
    {
        $this->vereisOpvolgen();
        $bevinding = $this->auditronde->bevindingen()->with('afwijking')->findOrFail($bevindingId);

        if ($bevinding->isGesloten()) {
            return;
        }

        // Volgordekwestie, geen rechtenkwestie: toon de reden (§6).
        $belemmering = $bevinding->belemmeringVoorSluiten();
        if ($belemmering !== null) {
            session()->flash('fout', $belemmering);

            return;
        }

        $this->teSluitenBevindingId = $bevinding->id;
        $this->sluitNotitie = '';
        $this->resetValidation();
        $this->toontSluitFormulier = true;
    }

    public function bevestigSluiten(): void
    {
        $this->vereisOpvolgen();

        $gevalideerd = $this->validate([
            'teSluitenBevindingId' => ['required'],
            'sluitNotitie' => ['required', 'string', 'max:1000'],
        ], [
            'sluitNotitie.required' => 'Noteer wat er met deze bevinding is gebeurd.',
        ], attributes: ['sluitNotitie' => 'afhandeling']);

        $bevinding = $this->auditronde->bevindingen()->with('afwijking')
            ->findOrFail($gevalideerd['teSluitenBevindingId']);

        // Tussen openen en bevestigen kan de wereld veranderd zijn.
        if ($bevinding->isGesloten() || $bevinding->belemmeringVoorSluiten() !== null) {
            $this->toontSluitFormulier = false;

            return;
        }

        $bevinding->update([
            'status' => 'gesloten',
            'gesloten_op' => now()->toDateString(),
            'gesloten_door_id' => auth()->id(),
            'afhandelingsnotitie' => $gevalideerd['sluitNotitie'],
        ]);

        $this->toontSluitFormulier = false;
        $this->reset(['teSluitenBevindingId', 'sluitNotitie']);
        $this->auditronde->load('bevindingen');

        session()->flash('melding', 'Bevinding gesloten.');
    }

    // --- Kopie voor de auditor (12h) ---------------------------------------

    protected function kopieBlok(): string
    {
        return 'auditmanagement';
    }

    /**
     * Het rondedossier als document: de kopgegevens met de status, de normatieve
     * scope met wat er met elk object is gebeurd, en de bevindingen eronder.
     *
     * Eén regel per object, ook als dat er honderd zijn: samenvatten maakt het
     * onbruikbaar als dekkingsbewijs, en dat is precies waar een externe auditor
     * dit document voor opvraagt.
     */
    protected function schermkopie(): Schermkopie
    {
        $ronde = $this->auditronde->load(['auditplan', 'auditor', 'organisatieEenheden', 'auditobjecten', 'bevindingen']);
        $statussen = $ronde->objectstatussen();
        $namen = Gebruiker::pluck('naam', 'id');
        $anoniem = Gebruiker::with('rollen')->get()->mapWithKeys(
            fn (Gebruiker $g) => [$g->id => $g->anoniemLabel()]
        );

        $objecten = $ronde->auditobjecten->sortBy([['groep', 'asc'], ['volgorde', 'asc']])->values();

        $bevindingen = $ronde->bevindingen()->with(['auditobject', 'afwijking'])->orderBy('id')->get();
        $open = $bevindingen->reject(fn (Bevinding $b) => $b->isGesloten())->count();

        return new Schermkopie(
            scherm: 'Auditronde — '.$ronde->typeLabel().', plan '.($ronde->auditplan?->jaar ?? '?'),
            kolommen: ['Object', 'Omschrijving', 'Afhandeling', 'Bron'],
            rijen: $objecten->map(fn (Auditobject $object) => [
                $object->refCode().($object->pivot->buiten_planning ? ' (bijgroei)' : ''),
                $object->omschrijving(),
                ucfirst(self::AFHANDELING_LABELS[$statussen[$object->id] ?? 'niet_behandeld'] ?? '—'),
                self::bronLabel(
                    (bool) $object->pivot->eigen_waarneming,
                    $object->pivot->gesproken_met_id,
                    $object->pivot->toelichting,
                    $anoniem,
                ),
            ])->all(),
            // De normatieve scope ís de omvang van dit document; er wordt niet op
            // gefilterd, dus het aantal is altijd het geheel.
            totaalRijen: $objecten->count(),
            toelichting: 'Het dossier van deze auditronde (§9.2): welk deel van de norm zij dekte, wat er '
                .'met elk object is gebeurd, en welke bevindingen dat opleverde. "Geen opmerkingen" betekent '
                .'dat het object is behandeld en in orde bevonden; alleen behandelde objecten tellen mee voor '
                .'de dekkingsmatrix. Personen staan als initialen met hun rol.',
            metPersoonsgegevens: true,
            eenheid: 'objecten in de normatieve scope',
            kenmerken: $this->kopiekenmerken($namen),
            bijlage: new Schermkopiebijlage(
                titel: 'Bevindingen',
                kolommen: ['Type', 'Betreft', 'Omschrijving', 'Bron', 'Status', 'Afhandeling'],
                rijen: $bevindingen->map(fn (Bevinding $b) => [
                    ucfirst(str_replace('_', ' ', $b->type)),
                    $b->auditobject?->refCode(),
                    $b->omschrijving,
                    self::bronLabel($b->eigen_waarneming, $b->gesprekspartnerId(), null, $anoniem),
                    ucfirst(str_replace('_', ' ', $b->status)),
                    $b->afhandelingsnotitie,
                ])->all(),
                omvangregel: $bevindingen->count() === 1
                    ? '1 bevinding, '.($open === 1 ? 'nog open.' : 'gesloten.')
                    : $bevindingen->count().' bevindingen, waarvan '.$open.' nog open.',
            ),
        );
    }

    /**
     * De kopgegevens van de ronde. De status staat er met zoveel woorden in: een
     * kopie van een lopende ronde is iets anders dan een kopie van een afgeronde,
     * en dat verschil mag niet uit de datums afgeleid hoeven worden.
     *
     * @param  Collection<int, string>  $namen
     * @return array<string, string>
     */
    private function kopiekenmerken($namen): array
    {
        $ronde = $this->auditronde;

        $status = ucfirst(str_replace('_', ' ', $ronde->status));

        if ($ronde->isAfgerond() && $ronde->uitgevoerd_op !== null) {
            $status .= ' — uitgevoerd op '.$ronde->uitgevoerd_op->format('d-m-Y');
        }

        // De bewijsstukken zoals het bewijspaneel ze op ditzelfde scherm toont:
        // alleen de titels, de bestanden zelf gaan niet mee.
        $bewijs = Bewijsstuk::query()
            ->whereHas('koppelingen', fn ($q) => $q
                ->where('entiteit_type', 'auditronde')->where('entiteit_id', $ronde->id))
            ->orderBy('naam')->pluck('naam');

        return array_filter([
            'Status' => $status,
            'Gepland op' => $ronde->gepland_op?->format('d-m-Y') ?? '—',
            'Uitvoerder' => $ronde->isIntern()
                ? ($ronde->auditor?->anoniemLabel() ?? '— nog niet toegewezen —')
                : ($ronde->extern_auditor_naam ?? '—'),
            'Organisatorische scope' => $ronde->organisatieEenheden->pluck('naam')->implode(', '),
            'Dekking' => $ronde->telt_mee_voor_dekking
                ? 'Telt mee voor de dekkingsmatrix'
                : 'Telt niet mee voor de dekkingsmatrix',
            'Bewijs' => $bewijs->isEmpty()
                ? 'Geen bewijsstukken gekoppeld'
                : $bewijs->count().' stuk(ken): '.$bewijs->implode(', '),
        ], fn (string $waarde) => $waarde !== '');
    }

    /**
     * De bron in één cel: eigen waarneming, een persoon, of — bij een object dat
     * niet is behandeld — de reden die daarvoor is opgegeven.
     *
     * @param  Collection<int, string>  $anoniem
     */
    private static function bronLabel(bool $eigenWaarneming, ?int $gebruikerId, ?string $toelichting, $anoniem): ?string
    {
        if ($eigenWaarneming) {
            return 'eigen waarneming';
        }

        if ($gebruikerId !== null) {
            return $anoniem[$gebruikerId] ?? '—';
        }

        return $toelichting;
    }

    public function render()
    {
        $bevindingen = $this->auditronde->bevindingen()
            ->with(['auditobject.maatregel', 'afwijking'])
            ->orderBy('id')
            ->get();

        // Voor de normatieve scope: de aangevinkte controls apart van de rest,
        // zodat de view de gekozen bovenaan zet en de overige (90+) onder een
        // uitklap wegbergt. Splitsen op de opgeslagen scope; het herschikken
        // volgt bij de volgende render (na 'Planning opslaan').
        $auditobjecten = Auditobject::actief()->with('maatregel')
            ->orderBy('groep')->orderBy('volgorde')
            ->get()
            ->mapWithKeys(fn (Auditobject $o) => [$o->id => $o->refCode().' '.$o->omschrijving()]);
        $gekozenIds = array_map('intval', $this->scopeObjecten);

        // Namen in één keer, niet per regel: elke bevinding en elk behandeld
        // object kan een gesprekspartner hebben (11d §3). Voor de keuzelijsten
        // alleen de actieve accounts; voor het tónen alle, anders verdwijnt de
        // naam bij een bevinding zodra iemand uit dienst gaat.
        $gesprekspartners = Gebruiker::orderBy('naam')->pluck('naam', 'id');
        $keuzes = $gesprekspartners->intersectByKeys(
            Gebruiker::where('status', 'actief')->pluck('id', 'id')
        )->all();

        return view('livewire.auditronde-detail', [
            'bevindingen' => $bevindingen,
            'objectstatussen' => $this->auditronde->objectstatussen(),
            'gesprekspartners' => $gesprekspartners->all(),
            'gebruikers' => $keuzes,
            // De gesprekspartnerlijst van een bevinding heeft één extra uitweg,
            // want niet elke bevinding komt uit een gesprek.
            'bronnen' => [self::EIGEN_WAARNEMING => 'Geen gesprek — eigen waarneming'] + $keuzes,
            'afhandelingLabels' => self::AFHANDELING_LABELS,
            'handmatigeAfhandelingen' => self::HANDMATIGE_AFHANDELINGEN,
            'onbehandeld' => $this->toontAfrondFormulier
                ? $this->auditronde->auditobjecten->whereIn('id', array_keys($this->afrondRedenen))
                : collect(),
            'types' => self::TYPES,
            'bevindingTypes' => self::BEVINDING_TYPES,
            'auditors' => $this->magPlannen()
                ? Gebruiker::where('status', 'actief')->orderBy('naam')->pluck('naam', 'id')->all()
                : [],
            'eenheden' => OrganisatieEenheid::orderBy('naam')->pluck('naam', 'id')->all(),
            'heeftObjecten' => $auditobjecten->isNotEmpty(),
            'gekozenObjecten' => $auditobjecten->filter(fn ($l, $id) => in_array($id, $gekozenIds, true))->all(),
            'overigeObjecten' => $auditobjecten->reject(fn ($l, $id) => in_array($id, $gekozenIds, true))->all(),
            // Waar een bevinding over kan gaan: alle actieve objecten, dus ook de
            // clausules uit H4-H10 — die waren vóór 11d onbereikbaar.
            'onderwerpen' => $auditobjecten->all(),
        ]);
    }
}
