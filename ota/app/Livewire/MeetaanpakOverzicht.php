<?php

namespace App\Livewire;

use App\Models\KpiDefinitie;
use App\Support\Meetbronnen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * De meetaanpak (§9.1): de KPI-catalogus met per KPI de berekeningswijze, de
 * norm, de definitieversie en de vastgelegde meetpunten — en sinds
 * implementatie/12e het scherm waarin de CISO die catalogus beheert.
 *
 * De metingen zelf blijven onveranderlijk en komen uit `isms:meet-kpis`; hier
 * wordt bepaald wát er gemeten wordt en waartegen het beoordeeld wordt. Dat is
 * de "aanpak op papier" die de auditor ook zonder trend al kan inzien.
 */
#[Layout('components.layouts.app')]
class MeetaanpakOverzicht extends Component
{
    private const BLOK = 'management-review-verbetercyclus';

    /** PDCA-volgorde voor de weergave; label per fase. */
    private const FASE_LABELS = [
        'plan' => 'Plan',
        'do' => 'Do',
        'check' => 'Check',
        'act' => 'Act',
    ];

    public bool $toontFormulier = false;

    public ?int $bewerktId = null;

    public string $naam = '';

    public string $fase = 'check';

    /** Leeg = handmatige KPI: de applicatie rekent niets uit (12e §2). */
    public string $meetbron = '';

    public string $eenheid = 'ratio';

    public string $richting = 'omhoog';

    public string $berekeningswijze = '';

    public string $streefwaarde = '';

    public string $signaalwaarde = '';

    public bool $actief = true;

    // --- Handmatig meetpunt (12e §5) ----------------------------------------

    public bool $toontMeting = false;

    public ?int $meetKpiId = null;

    public string $gemetenOp = '';

    public string $teller = '';

    public string $noemer = '';

    public string $meettoelichting = '';

    /** De vraag uit 12f §3: is bij een handmatige KPI de méthode veranderd? */
    public bool $toontMethodevraag = false;

    // --- Streefwaarde vaststellen (12e §9) ------------------------------------------

    public bool $toontStreefwaardeBevestiging = false;

    public ?int $streefwaardeKpiId = null;

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', [self::BLOK, 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public function nieuweKpi(): void
    {
        $this->vereisMuteren();
        $this->reset([
            'bewerktId', 'naam', 'fase', 'meetbron', 'eenheid', 'richting',
            'berekeningswijze', 'streefwaarde', 'signaalwaarde', 'actief',
        ]);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function bewerk(int $definitieId): void
    {
        $this->vereisMuteren();

        $definitie = KpiDefinitie::findOrFail($definitieId);

        $this->bewerktId = $definitie->id;
        $this->naam = $definitie->naam;
        $this->fase = $definitie->fase;
        $this->meetbron = $definitie->meetbron ?? '';
        $this->eenheid = $definitie->eenheid;
        $this->richting = $definitie->richting;
        $this->berekeningswijze = $definitie->berekeningswijze;
        $this->streefwaarde = $definitie->streefwaarde === null ? '' : (string) $definitie->streefwaarde;
        $this->signaalwaarde = $definitie->signaalwaarde === null ? '' : (string) $definitie->signaalwaarde;
        $this->actief = $definitie->actief;

        $this->resetValidation();
        $this->toontFormulier = true;
    }

    /**
     * De registry vult het formulier voor bij een gekozen meetbron. Een
     * suggestie en geen dwang: de gebruiker mag daarna een andere fase, richting
     * of formulering kiezen (12e §4).
     */
    public function updatedMeetbron(string $waarde): void
    {
        $voorstel = $waarde === '' ? null : Meetbronnen::voorstel($waarde);

        if ($voorstel === null) {
            return;
        }

        // De eenheid alleen voorstellen zolang hij nog niet vastligt; anders
        // biedt het scherm een wijziging aan die het model terecht weigert.
        if (! $this->eenheidLigtVast()) {
            $this->eenheid = $voorstel['eenheid'];
        }

        $this->richting = $voorstel['richting'];
        $this->berekeningswijze = $voorstel['berekeningswijze'];
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
        $this->bewerktId = null;
    }

    public function opslaan(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate($this->regels());

        // De applicatie kán niet zien of een gewijzigde methodetekst een echte
        // breuk in de reeks is: een spelfout herstellen en van meetmethode
        // wisselen zien er in de database identiek uit. Alleen wie het typt weet
        // het verschil, dus wordt het gevraagd (12f §3).
        if ($this->methodevraagNodig()) {
            $this->toontFormulier = false;
            $this->toontMethodevraag = true;

            return;
        }

        $this->bewaar($gevalideerd, methodeGewijzigd: null);
    }

    /**
     * Alleen bij een KPI die handmatig is én blijft. Wisselt de meetbron, dan is
     * dát de betekeniswijziging en hoogt het model de versie al op; de vraag zou
     * dan tot een tweede ophoging leiden (12f §5).
     */
    private function methodevraagNodig(): bool
    {
        if ($this->bewerktId === null || $this->meetbron !== '') {
            return false;
        }

        $definitie = KpiDefinitie::findOrFail($this->bewerktId);

        return $definitie->isHandmatig()
            && trim($this->berekeningswijze) !== trim($definitie->berekeningswijze);
    }

    public function beantwoordMethodevraag(bool $methodeGewijzigd): void
    {
        $this->vereisMuteren();
        abort_unless($this->toontMethodevraag, 403);

        $gevalideerd = $this->validate($this->regels());

        $this->toontMethodevraag = false;
        $this->bewaar($gevalideerd, $methodeGewijzigd);
    }

    /** Terug naar het formulier; de ingevoerde tekst blijft staan. */
    public function sluitMethodevraag(): void
    {
        $this->toontMethodevraag = false;
        $this->toontFormulier = true;
    }

    /**
     * @param  bool|null  $methodeGewijzigd  null = de vraag was niet van toepassing
     */
    private function bewaar(array $gevalideerd, ?bool $methodeGewijzigd): void
    {
        $waarden = [
            'naam' => $gevalideerd['naam'],
            'fase' => $gevalideerd['fase'],
            'meetbron' => $gevalideerd['meetbron'] ?: null,
            'richting' => $gevalideerd['richting'],
            'berekeningswijze' => $gevalideerd['berekeningswijze'],
            'streefwaarde' => $gevalideerd['streefwaarde'] === '' ? null : (float) $gevalideerd['streefwaarde'],
            'signaalwaarde' => $gevalideerd['signaalwaarde'] === '' ? null : (float) $gevalideerd['signaalwaarde'],
            'actief' => $this->actief,
        ];

        if ($this->bewerktId === null) {
            KpiDefinitie::create($waarden + [
                'sleutel' => KpiDefinitie::sleutelVoor($gevalideerd['naam']),
                'eenheid' => $gevalideerd['eenheid'],
                // Een streefwaarde die de gebruiker zelf intikt is per definitie
                // vastgesteld: hij heeft hem gekozen. De voorstel-status uit
                // 12e §9 bestaat alleen voor meegeleverde waarden.
                'streefwaarde_vastgesteld_op' => $waarden['streefwaarde'] === null ? null : now()->toDateString(),
                'definitie_versie' => 1,
            ]);

            $this->sluitFormulier();
            session()->flash('melding', 'KPI aangemaakt.');

            return;
        }

        $definitie = KpiDefinitie::findOrFail($this->bewerktId);

        // De eenheid alleen meesturen zolang hij vrij is; het model gooit anders,
        // en die uitzondering is het vangnet — niet de melding die de gebruiker
        // hoort te zien.
        if (! $definitie->eenheidLigtVast()) {
            $waarden['eenheid'] = $gevalideerd['eenheid'];
        }

        // Leeggemaakt: de vaststelling vervalt mee. Gewijzigd naar een
        // gevulde waarde: die heeft de gebruiker net gekozen, dus vastgesteld.
        // Ongewijzigd: niets aanraken — een voorstel adopteer je bewust met de
        // knop, niet door langs te lopen om de naam te corrigeren.
        if ($waarden['streefwaarde'] === null) {
            $waarden['streefwaarde_vastgesteld_op'] = null;
        } elseif ($waarden['streefwaarde'] !== $definitie->streefwaarde) {
            $waarden['streefwaarde_vastgesteld_op'] = now()->toDateString();
        }

        // Expliciet meegeven en niet aan de model-hook overlaten: die kent alleen
        // `meetbron` en `richting`. Zodra dit veld gezet is slaat de hook zijn
        // eigen bump over, dus een gelijktijdige richtingwijziging telt niet
        // dubbel op (12f §5).
        if ($methodeGewijzigd === true) {
            $waarden['definitie_versie'] = $definitie->definitie_versie + 1;
        }

        $definitie->update($waarden);

        // Alleen de ontkenning wordt apart vastgelegd. Bij "ja" staat de breuk al
        // in de trail — de opgehoogde `definitie_versie` ís het bewijs. Bij "nee"
        // zou er niets staan, terwijl juist dát de vraag is die een auditor bij
        // een verdachte trend stelt: heeft iemand hier een breuk weggeklikt?
        if ($methodeGewijzigd === false) {
            $definitie->schrijfAuditregel('gewijzigd', oud: null, nieuw: [
                'methode_ongewijzigd_verklaard' => true,
            ]);
        }

        $this->sluitFormulier();
        session()->flash('melding', $definitie->wasChanged('definitie_versie')
            ? "KPI opgeslagen. De definitieversie staat nu op v{$definitie->definitie_versie}, omdat de betekenis van de reeks veranderde."
            : 'KPI opgeslagen.');
    }

    /**
     * Eerst vragen, dan vaststellen. Een streefwaarde vaststellen is een
     * bestuurlijke daad met een gevolg dat niet uit de knop af te lezen is: vanaf dat moment
     * krijgt de KPI een oordeel, en de organisatie krijgt er een uitspraak bij
     * die een auditor zal navragen. Dat verdient een scherm dat zegt wát er
     * wordt vastgesteld, niet alleen een knop die het doet.
     */
    public function bevestigStreefwaarde(int $definitieId): void
    {
        $this->vereisMuteren();

        $definitie = KpiDefinitie::findOrFail($definitieId);
        abort_if($definitie->streefwaarde === null, 403);

        $this->streefwaardeKpiId = $definitie->id;
        $this->toontStreefwaardeBevestiging = true;
    }

    public function sluitStreefwaardeBevestiging(): void
    {
        $this->toontStreefwaardeBevestiging = false;
        $this->streefwaardeKpiId = null;
    }

    /**
     * Het meegeleverde voorstel adopteren als eigen norm (12e §9). Vanaf nu
     * kleurt hij de meetpunten en gaat hij mee de meetrij in; de handeling zelf
     * belandt in de audit trail, zodat "wie heeft die streefwaarde vastgesteld" een
     * antwoord heeft.
     */
    public function stelStreefwaardeVast(int $definitieId): void
    {
        $this->vereisMuteren();

        $definitie = KpiDefinitie::findOrFail($definitieId);

        // Zonder streefwaarde valt er niets vast te stellen.
        abort_if($definitie->streefwaarde === null, 403);

        $definitie->update(['streefwaarde_vastgesteld_op' => now()->toDateString()]);

        $this->sluitStreefwaardeBevestiging();
        session()->flash('melding', 'Streefwaarde vastgesteld. Vanaf het eerstvolgende meetpunt telt ze mee.');
    }

    /**
     * Een KPI mét metingen gaat op inactief, nooit weg: dat is het enige
     * onherstelbare in dit blok, en `actief = false` doet wat de gebruiker
     * bedoelt (stoppen met meten) zonder de historie te vernietigen (12e §6).
     */
    public function zetActief(int $definitieId, bool $actief): void
    {
        $this->vereisMuteren();

        KpiDefinitie::findOrFail($definitieId)->update(['actief' => $actief]);

        session()->flash('melding', $actief
            ? 'KPI weer actief.'
            : 'KPI op inactief gezet; de historie blijft staan.');
    }

    public function verwijder(int $definitieId): void
    {
        $this->vereisMuteren();

        $definitie = KpiDefinitie::findOrFail($definitieId);

        abort_unless($definitie->magVerwijderdWorden(), 403);

        $definitie->delete();

        session()->flash('melding', 'KPI verwijderd.');
    }

    // --- Handmatige meetpunten (12e §5) -------------------------------------

    public function nieuwMeetpunt(int $definitieId): void
    {
        $this->vereisMuteren();

        $definitie = KpiDefinitie::findOrFail($definitieId);

        // Alleen bij een handmatige KPI. Een berekende reeks met hand ingevoerde
        // punten ertussen is niet meer reproduceerbaar: het commando zou die
        // maand overslaan en niemand kan later nog zien welk punt waar vandaan
        // kwam.
        abort_unless($definitie->isHandmatig(), 403);

        $this->reset(['gemetenOp', 'teller', 'noemer', 'meettoelichting']);
        $this->resetValidation();

        $this->meetKpiId = $definitie->id;
        $this->gemetenOp = now()->toDateString();
        $this->toontMeting = true;
    }

    public function sluitMeting(): void
    {
        $this->toontMeting = false;
        $this->meetKpiId = null;
    }

    public function slaMeetpuntOp(): void
    {
        $this->vereisMuteren();

        $definitie = KpiDefinitie::findOrFail($this->meetKpiId);
        abort_unless($definitie->isHandmatig(), 403);

        $gevalideerd = $this->validate([
            // Geen meting in de toekomst: een meetpunt legt vast wat er wás.
            'gemetenOp' => ['required', 'date', 'before_or_equal:today'],
            'teller' => ['required', 'integer', 'min:0'],
            // Noemer 0 betekent "geen populatie" en dus geen meting (12 §5);
            // met de hand een lege noemer invoeren levert alleen een rij die als
            // 0% leest.
            'noemer' => ['required', 'integer', 'min:1'],
            'meettoelichting' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'gemetenOp' => 'meetdatum',
            'meettoelichting' => 'toelichting',
        ]);

        $datum = Carbon::parse($gevalideerd['gemetenOp']);

        if ($definitie->eenheid === 'ratio' && $gevalideerd['teller'] > $gevalideerd['noemer']) {
            $this->addError('teller', 'Bij een ratio kan de teller niet groter zijn dan de noemer.');

            return;
        }

        // Eén meetpunt per maand, net als het commando. Anders ontstaat er een
        // reeks met ongelijke tussenafstanden en rekent `Kpitrend::basis()` op
        // de verkeerde vergelijkingsbasis.
        $bestaat = $definitie->metingen()
            ->whereYear('gemeten_op', $datum->year)
            ->whereMonth('gemeten_op', $datum->month)
            ->exists();

        if ($bestaat) {
            $this->addError('gemetenOp', 'Er is voor '.$datum->translatedFormat('F Y')
                .' al een meetpunt. Een meting is onveranderlijk; een correctie is een '
                .'nieuw meetpunt in een volgende periode, met een toelichting.');

            return;
        }

        $definitie->metingen()->create([
            'gemeten_op' => $datum,
            'teller' => $gevalideerd['teller'],
            'noemer' => $gevalideerd['noemer'],
            // Dezelfde kopieerregel als het commando: de herkomst van een
            // meetpunt mag niets uitmaken voor wat er in de rij staat.
            'definitie_versie' => $definitie->definitie_versie,
            'streefwaarde' => $definitie->vastgesteldeStreefwaarde(),
            'signaalwaarde' => $definitie->vastgesteldeSignaalwaarde(),
            'toelichting' => $gevalideerd['meettoelichting'] ?: null,
            'ingevoerd_door_id' => auth()->id(),
        ]);

        $this->sluitMeting();
        session()->flash('melding', 'Meetpunt vastgelegd.');
    }

    /** @return array<string, array<int, mixed>> */
    private function regels(): array
    {
        $maximum = $this->eenheid === 'ratio' ? 100 : 99999.9;

        return [
            'naam' => ['required', 'string', 'max:255'],
            'fase' => ['required', Rule::in(array_keys(self::FASE_LABELS))],
            // Leeg is geldig en betekent handmatig; een niet-lege waarde moet in
            // de registry bestaan, anders meet de KPI nooit iets (12e §1).
            'meetbron' => ['nullable', Rule::in(array_keys(Meetbronnen::keuzelijst()))],
            'eenheid' => ['required', Rule::in(['ratio', 'dagen', 'aantal'])],
            'richting' => ['required', Rule::in(['omhoog', 'omlaag'])],
            'berekeningswijze' => ['required', 'string', 'max:2000'],
            'streefwaarde' => ['nullable', 'numeric', 'min:0', 'max:'.$maximum],
            'signaalwaarde' => [
                'nullable', 'numeric', 'min:0', 'max:'.$maximum,
                // Een signaalwaarde aan de verkeerde kant van de streefwaarde
                // maakt de semafoor betekenisloos: de oranje band bestaat dan
                // niet en elk punt is groen of rood.
                fn (string $attribuut, mixed $waarde, callable $fout) => $this->toetsSignaalgrens($waarde, $fout),
            ],
        ];
    }

    private function toetsSignaalgrens(mixed $waarde, callable $fout): void
    {
        if ($waarde === '' || $waarde === null || $this->streefwaarde === '') {
            return;
        }

        $streef = (float) $this->streefwaarde;
        $signaal = (float) $waarde;

        if ($this->richting === 'omlaag' ? $signaal <= $streef : $signaal >= $streef) {
            $fout($this->richting === 'omlaag'
                ? 'Bij richting omlaag hoort de signaalwaarde bóven de streefwaarde te liggen.'
                : 'Bij richting omhoog hoort de signaalwaarde ónder de streefwaarde te liggen.');
        }
    }

    private function eenheidLigtVast(): bool
    {
        return $this->bewerktId !== null
            && KpiDefinitie::findOrFail($this->bewerktId)->eenheidLigtVast();
    }

    public function render()
    {
        $definities = KpiDefinitie::query()
            ->with([
                'metingen' => fn ($q) => $q->orderByDesc('gemeten_op'),
                'metingen.ingevoerdDoor:id,naam',
            ])
            ->orderBy('naam')
            ->get();

        // Gegroepeerd en geordend volgens de PDCA-fasen; lege fasen (bijv. Act,
        // nog niet geseed) vallen weg zodat het scherm toont wat er echt is.
        $perFase = collect(self::FASE_LABELS)
            ->mapWithKeys(fn (string $label, string $fase) => [
                $fase => $definities->where('fase', $fase)->values(),
            ])
            ->filter->isNotEmpty();

        return view('livewire.meetaanpak-overzicht', [
            'faseLabels' => self::FASE_LABELS,
            'perFase' => $perFase,
            'aantalMetingen' => $definities->sum(fn (KpiDefinitie $d) => $d->metingen->count()),
            'meetbronnen' => Meetbronnen::keuzelijst(),
            'eenheidVast' => $this->eenheidLigtVast(),
            'streefwaardeKpi' => $this->streefwaardeKpiId === null
                ? null
                : $definities->firstWhere('id', $this->streefwaardeKpiId),
        ]);
    }
}
