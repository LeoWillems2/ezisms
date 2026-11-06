<?php

namespace App\Mcp\Tools;

use App\Livewire\AuditrondeDetail;
use App\Models\Auditobject;
use App\Models\Auditplan;
use App\Models\Auditprogramma;
use App\Models\Auditronde;
use App\Models\Bevinding;
use App\Models\Gebruiker;
use App\Support\Assistent\Aanroepcontext;
use App\Support\Dekkingsmatrixstand;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Auditprogramma, dekkingsmatrix, auditrondes en bevindingen
 * (implementatie/14b §5), zoals de schermen onder /audits ze tonen.
 *
 * Alle schermen onder /audits staan op `auditmanagement`/`lezen` en kennen geen
 * record-scoping bij het lezen: de onafhankelijkheid van de auditor
 * (implementatie/11 §4) is een schrijfregel (`magBevindingBewerkenDoor`), en
 * schrijven doet deze tool niet. De matrix komt uit {@see Dekkingsmatrixstand},
 * dezelfde klasse als het scherm. De bron van een behandeling of bevinding staat
 * met naam, zoals op het rondedossier (de documentkopie anonimiseert, het
 * scherm niet).
 */
#[IsReadOnly]
#[Description('Interne en externe audits: het meerjarige auditprogramma, de dekkingsmatrix (welke clausules H4–H10 en Bijlage A-maatregelen in welk programmajaar gepland, uitgevoerd of een gat zijn), de auditrondes met status, en de bevindingen met type, status en opvolging. Gebruik dit voor vragen over auditdekking, ontbrekende audits, openstaande non-conformiteiten of de inhoud van één auditronde (geef dan auditronde_id).')]
class AuditDekking extends IsmsTool
{
    private const STANDAARD_LIMIET = 50;

    private const OPENSTAAND = 'openstaand';

    /** @var array<string, string> */
    private const CEL_LABELS = [
        'uitgevoerd' => 'Uitgevoerd',
        'gepland' => 'Gepland',
        'gat' => 'Gat (gepland, niet uitgevoerd)',
        'leeg' => 'Niet gepland',
    ];

    /** @var array<string, string> */
    private const BEVINDING_TYPES = [
        'non_conformiteit_major' => 'Non-conformiteit major',
        'non_conformiteit_minor' => 'Non-conformiteit minor',
        'observatie' => 'Observatie',
        'verbeterkans' => 'Verbeterkans',
    ];

    /** @var array<string, string> */
    private const BEVINDING_STATUSSEN = [
        'open' => 'Open',
        'non_conformiteit_gestart' => 'Non-conformiteit gestart',
        'gesloten' => 'Gesloten',
    ];

    /** @var array<string, string> */
    private const RONDE_STATUSSEN = [
        'gepland' => 'Gepland',
        'in_uitvoering' => 'In uitvoering',
        'afgerond' => 'Afgerond',
    ];

    protected function vereist(): ?array
    {
        // Gelijk aan de routegroep van /audits.
        return ['auditmanagement', 'lezen'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'auditprogramma_id' => $schema->integer()
                ->description('Welk auditprogramma voor de matrix. Standaard het actieve, anders het meest recente.'),
            'jaar' => $schema->integer()
                ->min(2000)->max(2100)
                ->description('Alleen auditrondes (en hun bevindingen) uit het auditplan van dit jaar.'),
            'auditronde_id' => $schema->integer()
                ->description('Eén auditronde in detail: de normatieve scope met wat er per object is gebeurd, en alle bevindingen van die ronde.'),
            'alleen_gaten' => $schema->boolean()
                ->description('Toon in de matrix alleen objecten met een gat of die in dit programma nog nooit zijn gedekt.'),
            'bevinding_status' => $schema->string()
                ->enum([self::OPENSTAAND, ...array_keys(self::BEVINDING_STATUSSEN), 'alle'])
                ->description('Filter op de bevindingenlijst (niet bij auditronde_id). Standaard "openstaand" (alles wat niet gesloten is), zoals het bevindingenregister.'),
            'limiet' => $schema->integer()
                ->min(1)->max(self::MAX_RIJEN)
                ->description('Hoeveel rondes en bevindingen hooguit. Standaard '.self::STANDAARD_LIMIET.'.'),
        ];
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $invoer = $request->validate([
            'auditprogramma_id' => ['sometimes', 'integer'],
            'jaar' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'auditronde_id' => ['sometimes', 'integer'],
            'alleen_gaten' => ['sometimes', 'boolean'],
            'bevinding_status' => ['sometimes', 'string', 'in:'.implode(',', [self::OPENSTAAND, ...array_keys(self::BEVINDING_STATUSSEN), 'alle'])],
            'limiet' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RIJEN],
        ]);

        $limiet = (int) ($invoer['limiet'] ?? self::STANDAARD_LIMIET);

        if (isset($invoer['auditronde_id'])) {
            $ronde = Auditronde::find((int) $invoer['auditronde_id']);

            if ($ronde === null) {
                return Response::error('Auditronde '.$invoer['auditronde_id'].' bestaat niet.');
            }

            $uitvoer = $this->rondedossier($ronde);
            app(Aanroepcontext::class)->resultaten(count($uitvoer['bevindingen']));

            return Response::structured($uitvoer);
        }

        $programmaId = $invoer['auditprogramma_id'] ?? Dekkingsmatrixstand::standaardProgrammaId();
        $programma = $programmaId !== null ? Auditprogramma::find($programmaId) : null;

        $rondes = $this->rondes($invoer['jaar'] ?? null);
        $bevindingen = $this->bevindingen($invoer['jaar'] ?? null, $invoer['bevinding_status'] ?? self::OPENSTAAND);

        $uitvoer = [
            'programma' => $programma !== null ? $this->programma($programma) : null,
            'andere_programmas' => Auditprogramma::orderByDesc('start_datum')
                ->when($programma !== null, fn ($q) => $q->whereKeyNot($programma->id))
                ->get()
                ->map(fn (Auditprogramma $p) => [
                    'id' => $p->id,
                    'naam' => $p->naam,
                    'status' => self::enum($p->status),
                    'venster' => $p->venster(),
                ])->all(),
            'dekking' => $programma !== null
                ? $this->dekking(Dekkingsmatrixstand::voor($programma), (bool) ($invoer['alleen_gaten'] ?? false))
                : null,
            'signalen' => $this->signalen(),
            'rondes' => [
                'totaal' => $rondes->count(),
                'lijst' => $rondes->take($limiet)->map(fn (Auditronde $r) => $this->ronde($r))->values()->all(),
                'url' => route('audits.index'),
            ],
            'bevindingen' => [
                'filter' => $invoer['bevinding_status'] ?? self::OPENSTAAND,
                'totaal' => $bevindingen->count(),
                'lijst' => $bevindingen->take($limiet)->map(fn (Bevinding $b) => $this->bevinding($b))->values()->all(),
                'url' => route('audits.bevindingen'),
            ],
        ];

        app(Aanroepcontext::class)->resultaten(
            count($uitvoer['rondes']['lijst']) + count($uitvoer['bevindingen']['lijst'])
                + count($uitvoer['dekking']['objecten'] ?? []),
        );

        return Response::structured($uitvoer);
    }

    /** @return array<string, mixed> */
    private function programma(Auditprogramma $programma): array
    {
        return [
            'id' => $programma->id,
            'naam' => $programma->naam,
            'status' => self::enum($programma->status),
            'aard' => self::enum($programma->aard),
            'start_datum' => $programma->start_datum?->toDateString(),
            'eind_datum' => $programma->eindDatum()->toDateString(),
            'aantal_jaren' => $programma->aantal_jaren,
            'programmajaren' => array_map(fn (array $jaar) => [
                'nummer' => $jaar['nummer'],
                'start' => $jaar['start']->toDateString(),
                'eind' => $jaar['eind']->toDateString(),
            ], $programma->programmajaren()),
            'jaarplannen' => $programma->auditplannen()->orderBy('programmajaar')->orderBy('jaar')->get()
                ->map(fn (Auditplan $plan) => [
                    'jaar' => $plan->jaar,
                    'programmajaar' => $plan->programmajaar,
                    'status' => self::enum($plan->status),
                ])->all(),
            'url' => route('audits.programma'),
        ];
    }

    /** @return array<string, mixed> */
    private function dekking(Dekkingsmatrixstand $stand, bool $alleenGaten): array
    {
        $nummers = array_column($stand->programmajaren, 'nummer');

        $rijen = $stand->objecten->map(function (Auditobject $object) use ($stand, $nummers) {
            $cellen = $stand->cellen[$object->id] ?? [];

            return [
                'ref' => $object->refCode(),
                'omschrijving' => $object->omschrijving(),
                'soort' => $object->soort,
                'groep' => $object->groep,
                'per_programmajaar' => array_map(fn (int $nummer) => [
                    'programmajaar' => $nummer,
                    ...self::enum($cellen[$nummer] ?? 'leeg', self::CEL_LABELS),
                ], $nummers),
                'ooit_gedekt' => in_array('uitgevoerd', $cellen, true),
                'gat_in_programmajaren' => array_keys(array_filter($cellen, fn ($c) => $c === 'gat')),
            ];
        });

        if ($alleenGaten) {
            $rijen = $rijen->filter(fn (array $rij) => $rij['gat_in_programmajaren'] !== [] || ! $rij['ooit_gedekt']);
        }

        $rijen = $rijen->values();

        return [
            'kpi' => $stand->kpi,
            'gaten_getoond' => ! $stand->programma->isVoorbereiding(),
            'totaal' => $rijen->count(),
            'objecten' => $rijen->take(self::MAX_RIJEN)->all(),
            'url' => route('audits.dekking'),
        ];
    }

    /** Dezelfde signalen als het auditsoverzicht. @return array<string, mixed> */
    private function signalen(): array
    {
        $openPerType = Bevinding::query()
            ->where('status', '!=', 'gesloten')
            ->selectRaw('type, count(*) as aantal')
            ->groupBy('type')
            ->pluck('aantal', 'type');

        $laatsteIntern = Auditronde::query()
            ->whereIn('type', Auditronde::INTERNE_TYPEN)
            ->where('status', 'afgerond')
            ->orderByDesc('uitgevoerd_op')
            ->first();

        return [
            'openstaande_bevindingen' => (int) $openPerType->sum(),
            'openstaand_per_type' => $openPerType->map(fn ($aantal, $type) => [
                ...self::enum($type, self::BEVINDING_TYPES),
                'aantal' => (int) $aantal,
            ])->values()->all(),
            'laatste_interne_audit' => $laatsteIntern?->uitgevoerd_op?->toDateString(),
            'dagen_sinds_interne_audit' => $laatsteIntern?->uitgevoerd_op
                ? (int) $laatsteIntern->uitgevoerd_op->diffInDays(now())
                : null,
        ];
    }

    /** @return Collection<int, Auditronde> */
    private function rondes(?int $jaar): Collection
    {
        // De volgorde van het auditsoverzicht: nieuwste plan eerst, daarbinnen
        // de laatst geplande ronde.
        return Auditronde::query()
            ->with(['auditplan.auditprogramma', 'auditor'])
            ->withCount([
                'bevindingen',
                'bevindingen as open_bevindingen_count' => fn ($q) => $q->where('status', '!=', 'gesloten'),
            ])
            ->when($jaar !== null, fn ($q) => $q->whereHas('auditplan', fn ($p) => $p->where('jaar', $jaar)))
            ->get()
            ->sortBy([
                fn ($a, $b) => ($b->auditplan?->jaar ?? 0) <=> ($a->auditplan?->jaar ?? 0),
                fn ($a, $b) => ($b->gepland_op?->timestamp ?? 0) <=> ($a->gepland_op?->timestamp ?? 0),
                fn ($a, $b) => $b->id <=> $a->id,
            ])
            ->values();
    }

    /** @return array<string, mixed> */
    private function ronde(Auditronde $ronde): array
    {
        return [
            'id' => $ronde->id,
            'type' => self::enum($ronde->type, [$ronde->type => $ronde->typeLabel()]),
            'status' => self::enum($ronde->status, self::RONDE_STATUSSEN),
            'auditplan_jaar' => $ronde->auditplan?->jaar,
            'cyclus' => $ronde->auditplan?->cyclusLabel(),
            'gepland_op' => $ronde->gepland_op?->toDateString(),
            'uitgevoerd_op' => $ronde->uitgevoerd_op?->toDateString(),
            'uitvoerder' => $ronde->isIntern() ? $ronde->auditor?->naam : $ronde->extern_auditor_naam,
            'telt_mee_voor_dekking' => (bool) $ronde->telt_mee_voor_dekking,
            'bevindingen' => (int) $ronde->bevindingen_count,
            'open_bevindingen' => (int) $ronde->open_bevindingen_count,
            'url' => route('audits.ronde', $ronde),
        ];
    }

    /**
     * Het bevindingenregister: dezelfde filter en volgorde als
     * `BevindingenOverzicht` (zwaarste eerst).
     *
     * @return Collection<int, Bevinding>
     */
    private function bevindingen(?int $jaar, string $status): Collection
    {
        return Bevinding::query()
            ->with(['auditronde.auditplan', 'auditobject.maatregel', 'afwijking'])
            ->when($status === self::OPENSTAAND, fn ($q) => $q->where('status', '!=', 'gesloten'))
            ->when(isset(self::BEVINDING_STATUSSEN[$status]), fn ($q) => $q->where('status', $status))
            ->when($jaar !== null, fn ($q) => $q->whereHas('auditronde.auditplan', fn ($p) => $p->where('jaar', $jaar)))
            ->orderByRaw("case type
                when 'non_conformiteit_major' then 1
                when 'non_conformiteit_minor' then 2
                when 'observatie' then 3
                else 4 end")
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<int, string>|null  $namen  gebruiker-id => naam, voor de bron (alleen in het rondedossier)
     * @return array<string, mixed>
     */
    private function bevinding(Bevinding $bevinding, ?array $namen = null): array
    {
        $afwijking = $bevinding->afwijking;

        $rij = [
            'id' => $bevinding->id,
            'type' => self::enum($bevinding->type, self::BEVINDING_TYPES),
            'status' => self::enum($bevinding->status, self::BEVINDING_STATUSSEN),
            'omschrijving' => $bevinding->omschrijving,
            'betreft' => $bevinding->auditobject?->auditOmschrijving(),
            'auditronde' => $bevinding->auditronde?->auditOmschrijving(),
            'auditronde_id' => $bevinding->auditronde_id,
            'gesloten_op' => $bevinding->gesloten_op?->toIso8601String(),
            'afhandelingsnotitie' => $bevinding->afhandelingsnotitie,
            'opvolging' => $afwijking === null ? null : [
                'afwijking_id' => $afwijking->id,
                'gesloten' => $afwijking->isGesloten(),
                'gesloten_op' => $afwijking->gesloten_op?->toIso8601String(),
                'url' => route('afwijkingen.detail', $afwijking),
            ],
            'url' => $bevinding->auditronde_id !== null
                ? route('audits.ronde', $bevinding->auditronde_id)
                : route('audits.bevindingen'),
        ];

        if ($namen !== null) {
            $partner = $bevinding->gesprekspartnerId();
            $rij['bron'] = $bevinding->eigen_waarneming
                ? 'eigen waarneming'
                : ($partner !== null ? ($namen[$partner] ?? 'onbekend') : null);
        }

        return $rij;
    }

    /**
     * Eén ronde zoals het rondedossier haar toont: kop, normatieve scope met de
     * afhandeling per object (via `Auditronde::objectstatussen()`, dezelfde
     * afleiding als het scherm), en alle bevindingen.
     *
     * @return array<string, mixed>
     */
    private function rondedossier(Auditronde $ronde): array
    {
        $ronde->load(['auditplan', 'auditor', 'organisatieEenheden', 'auditobjecten.maatregel', 'bevindingen']);
        $ronde->loadCount([
            'bevindingen',
            'bevindingen as open_bevindingen_count' => fn ($q) => $q->where('status', '!=', 'gesloten'),
        ]);

        // Alle namen, ook van wie uit dienst is: anders verdwijnt de bron bij
        // een oude ronde (zoals op het scherm).
        $namen = Gebruiker::pluck('naam', 'id')->all();
        $statussen = $ronde->objectstatussen();

        $scope = $ronde->auditobjecten
            ->sortBy([['volgorde', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (Auditobject $object) use ($statussen, $namen) {
                $status = $statussen[$object->id] ?? 'niet_behandeld';

                return [
                    'ref' => $object->refCode(),
                    'omschrijving' => $object->omschrijving(),
                    'afhandeling' => self::enum($status, array_map('ucfirst', AuditrondeDetail::AFHANDELING_LABELS)),
                    'bron' => match (true) {
                        (bool) $object->pivot->eigen_waarneming => 'eigen waarneming',
                        $object->pivot->gesproken_met_id !== null => $namen[$object->pivot->gesproken_met_id] ?? 'onbekend',
                        default => null,
                    },
                    'toelichting' => $status === 'niet_toegekomen' ? $object->pivot->toelichting : null,
                    'buiten_planning' => (bool) $object->pivot->buiten_planning,
                ];
            });

        $bevindingen = $ronde->bevindingen()
            ->with(['auditronde.auditobjecten', 'auditronde.auditplan', 'auditobject.maatregel', 'afwijking'])
            ->orderBy('id')
            ->get();

        return [
            'ronde' => [
                ...$this->ronde($ronde),
                'organisatorische_scope' => $ronde->organisatieEenheden->pluck('naam')->values()->all(),
            ],
            'normatieve_scope' => $scope->all(),
            'bevindingen' => $bevindingen->map(fn (Bevinding $b) => $this->bevinding($b, $namen))->all(),
        ];
    }
}
