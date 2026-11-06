<?php

namespace App\Mcp\Tools;

use App\Models\Afwijking;
use App\Models\CorrigerendeMaatregel;
use App\Models\Effectiviteitstoets;
use App\Models\Grondoorzaak;
use App\Models\Incident;
use App\Models\IncidentMelding;
use App\Support\Assistent\Aanroepcontext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Incidenten en afwijkingen (implementatie/14b §5), zoals IncidentDetail en
 * AfwijkingDetail ze tonen.
 *
 * Toegang zoals de routes: beide detailschermen staan op
 * incident-afwijkingenbeheer `uitvoeren`. Incidenten via `Incident::zichtbaar()`
 * (zonder volledige inzage alleen de eigen meldingen), afwijkingen via
 * `Afwijking::zichtbaar()` (de regel van het detailscherm).
 *
 * Wat de schermen alleen met `muteren` tonen, laat deze tool zonder dat recht
 * ook weg: bij een incident de gekoppelde asset en het risico, het
 * kennisnamemoment, de raakvlakvragen en de meldplichtmotivatie (de melder ziet
 * alleen de stand); bij een afwijking de eigenaar.
 */
#[IsReadOnly]
#[Description('Incidenten en afwijkingen (non-conformiteiten, §10.2) met status, ernst, datums, de externe meldplicht (AVG/Cyberbeveiligingswet: stand van de beoordeling en de meldverplichtingen met uiterlijke datum en of ze op tijd zijn gedaan), grondoorzaken en corrigerende maatregelen met eigenaar, deadline, status en effectiviteitstoetsen. Gebruik dit voor vragen over incidentafhandeling, meldtermijnen, de CAPA-cyclus of de stand van corrigerende maatregelen.')]
class Incidenten extends IsmsTool
{
    private const STANDAARD_LIMIET = 50;

    private const BLOK = 'incident-afwijkingenbeheer';

    private const INCIDENTSTATUS = ['gemeld' => 'Gemeld', 'in_onderzoek' => 'In onderzoek', 'opgelost' => 'Opgelost', 'gesloten' => 'Gesloten'];

    private const AFWIJKINGSTATUS = ['open' => 'Open', 'analyse' => 'Analyse', 'actie_lopend' => 'Actie lopend', 'gesloten' => 'Gesloten'];

    private const MAATREGELSTATUS = ['open' => 'Open', 'in_uitvoering' => 'In uitvoering', 'voltooid' => 'Voltooid'];

    private const ERNST = ['laag' => 'Laag', 'midden' => 'Midden', 'hoog' => 'Hoog', 'kritiek' => 'Kritiek'];

    private const BRON = ['audit_bevinding' => 'Auditbevinding', 'incident' => 'Incident', 'interne_signalering' => 'Interne signalering'];

    private const MELDPLICHTSTAND = [
        'niet_beoordeeld' => 'Nog niet beoordeeld',
        'geen_meldplicht' => 'Beoordeeld: raakt de externe meldplicht niet',
        'beoordeling_loopt' => 'Raakt de meldplicht; de beoordeling loopt nog',
        'meldingsplichtig' => 'Extern meldingsplichtig',
        'niet_meldingsplichtig' => 'Beoordeeld: niet extern meldingsplichtig',
    ];

    protected function vereist(): ?array
    {
        // Overgenomen van de routes van incidenten.detail en afwijkingen.detail.
        return [self::BLOK, 'uitvoeren'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'soorten' => $schema->array()
                ->items($schema->string()->enum(['incident', 'afwijking']))
                ->description('Beperk tot incidenten of afwijkingen. Weggelaten: beide.'),
            'status' => $schema->string()
                ->enum(array_values(array_unique([...array_keys(self::INCIDENTSTATUS), ...array_keys(self::AFWIJKINGSTATUS)])))
                ->description('Alleen deze status. Incidenten: gemeld, in_onderzoek, opgelost, gesloten. Afwijkingen: open, analyse, actie_lopend, gesloten.'),
            'open_only' => $schema->boolean()
                ->description('Alleen wat nog niet gesloten is.'),
            'van' => $schema->string()->format('date')
                ->description('Gemeld (incident) of aangemaakt (afwijking) vanaf deze datum, JJJJ-MM-DD.'),
            'tot' => $schema->string()->format('date')
                ->description('Gemeld of aangemaakt tot en met deze datum, JJJJ-MM-DD.'),
            'limiet' => $schema->integer()->min(1)->max(self::MAX_RIJEN)
                ->description('Hoeveel rijen per soort hooguit, nieuwste eerst. Standaard '.self::STANDAARD_LIMIET.'.'),
        ];
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $invoer = $request->validate([
            'soorten' => ['sometimes', 'array'],
            'soorten.*' => ['string', 'in:incident,afwijking'],
            'status' => ['sometimes', 'string', 'max:50'],
            'open_only' => ['sometimes', 'boolean'],
            'van' => ['sometimes', 'date'],
            'tot' => ['sometimes', 'date'],
            'limiet' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RIJEN],
        ]);

        $soorten = ($invoer['soorten'] ?? []) ?: ['incident', 'afwijking'];
        $limiet = (int) ($invoer['limiet'] ?? self::STANDAARD_LIMIET);
        $van = isset($invoer['van']) ? Carbon::parse($invoer['van'])->startOfDay() : null;
        $tot = isset($invoer['tot']) ? Carbon::parse($invoer['tot'])->endOfDay() : null;
        $status = $invoer['status'] ?? null;
        $alleenOpen = (bool) ($invoer['open_only'] ?? false);
        $magMuteren = Gate::allows('heeft-niveau', [self::BLOK, 'muteren']);

        $uitvoer = [];
        $aantal = 0;

        if (in_array('incident', $soorten, true)) {
            $query = Incident::query()
                ->zichtbaar()
                ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
                ->when($alleenOpen, fn (Builder $q) => $q->where('status', '!=', 'gesloten'))
                ->when($van !== null, fn (Builder $q) => $q->where('gemeld_op', '>=', $van))
                ->when($tot !== null, fn (Builder $q) => $q->where('gemeld_op', '<=', $tot));

            $totaal = (clone $query)->count();
            $rijen = $query
                ->with(['melder:id,naam', 'sluiter:id,naam', 'afwijkingen', 'meldingen', 'asset:id,naam', 'risico:id,titel'])
                ->orderByDesc('gemeld_op')
                ->limit($limiet)
                ->get();

            $uitvoer['incidenten'] = [
                'totaal' => $totaal,
                'rijen' => $rijen->map(fn (Incident $i) => $this->incident($i, $magMuteren))->all(),
                'url' => route('incidenten.index'),
            ];
            $aantal += $rijen->count();
        }

        if (in_array('afwijking', $soorten, true)) {
            $query = Afwijking::query()
                ->zichtbaar()
                ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
                ->when($alleenOpen, fn (Builder $q) => $q->whereNull('gesloten_op'))
                ->when($van !== null, fn (Builder $q) => $q->where('created_at', '>=', $van))
                ->when($tot !== null, fn (Builder $q) => $q->where('created_at', '<=', $tot));

            $totaal = (clone $query)->count();
            $rijen = $query
                ->with([
                    'incident:id,titel', 'sluiter:id,naam', 'eigenaar:id,naam',
                    'grondoorzaken' => fn ($q) => $q->orderBy('id'),
                    'maatregelen' => fn ($q) => $q->orderBy('id'),
                    'maatregelen.eigenaar:id,naam', 'maatregelen.toetsen.uitvoerder:id,naam',
                ])
                ->orderByDesc('id')
                ->limit($limiet)
                ->get();

            $uitvoer['afwijkingen'] = [
                'totaal' => $totaal,
                'rijen' => $rijen->map(fn (Afwijking $a) => $this->afwijking($a, $magMuteren))->all(),
            ];
            // De lijst /afwijkingen vraagt `muteren`; zonder dat recht is er
            // alleen het detailscherm.
            if ($magMuteren) {
                $uitvoer['afwijkingen']['url'] = route('afwijkingen.index');
            }
            $aantal += $rijen->count();
        }

        app(Aanroepcontext::class)->resultaten($aantal);

        return Response::structured($uitvoer);
    }

    /** @return array<string, mixed> */
    private function incident(Incident $incident, bool $magMuteren): array
    {
        $rij = [
            'id' => $incident->id,
            'titel' => $incident->titel,
            'omschrijving' => $incident->omschrijving,
            'status' => self::enum($incident->status, self::INCIDENTSTATUS),
            'ernst' => self::enum($incident->ernst, self::ERNST),
            'gemeld_door' => $incident->melder?->naam,
            'gemeld_op' => $incident->gemeld_op?->toIso8601String(),
            'gesloten_op' => $incident->gesloten_op?->toIso8601String(),
            'gesloten_door' => $incident->gesloten_op ? $incident->sluiter?->naam : null,
            'doorlooptijd_dagen' => $incident->doorlooptijdInDagen(),
            'geen_afwijking_reden' => $incident->gesloten_op ? $incident->geen_afwijking_reden : null,
            'meldplicht' => $this->meldplicht($incident, $magMuteren),
            // Op het scherm staat de lijst voor iedereen; de link alleen met muteren.
            'afwijkingen' => $incident->afwijkingen->map(fn (Afwijking $a) => array_filter([
                'id' => $a->id,
                'omschrijving' => $a->auditOmschrijving(),
                'status' => self::enum($a->status, self::AFWIJKINGSTATUS),
                'url' => $magMuteren ? route('afwijkingen.detail', $a) : null,
            ], fn ($w) => $w !== null))->values()->all(),
            'url' => route('incidenten.detail', $incident),
        ];

        // Asset en risico staan op het scherm alleen in het muteerformulier.
        if ($magMuteren) {
            $rij['asset'] = $incident->asset === null ? null : [
                'id' => $incident->asset->id,
                'naam' => $incident->asset->naam,
                'url' => Gate::allows('heeft-niveau', ['asset-classificatie', 'lezen']) ? route('assets.detail', $incident->asset) : null,
            ];
            $rij['risico'] = $incident->risico === null ? null : [
                'id' => $incident->risico->id,
                'titel' => $incident->risico->titel,
                'url' => Gate::allows('heeft-niveau', ['risico-soa', 'lezen']) ? route('risicos.detail', $incident->risico) : null,
            ];
        }

        return $rij;
    }

    /**
     * De stand zoals de melder hem leest, plus voor wie mag muteren de
     * antwoorden zelf. De motivatie bevat een inschatting die bij de
     * beoordelaar hoort; het scherm toont haar de melder niet.
     *
     * @return array<string, mixed>
     */
    private function meldplicht(Incident $incident, bool $magMuteren): array
    {
        $stand = match (true) {
            $incident->raakt_persoonsgegevens === null => 'niet_beoordeeld',
            ! $incident->heeftDocumentatieplicht() => 'geen_meldplicht',
            $incident->extern_meldingsplichtig === null => 'beoordeling_loopt',
            $incident->extern_meldingsplichtig => 'meldingsplichtig',
            default => 'niet_meldingsplichtig',
        };

        $meldplicht = ['stand' => self::enum($stand, self::MELDPLICHTSTAND)];

        if ($magMuteren) {
            $meldplicht += [
                'kennisname_op' => $incident->kennisname_op?->toIso8601String(),
                'raakt_persoonsgegevens' => $incident->raakt_persoonsgegevens,
                'is_netwerk_informatie_incident' => $incident->is_netwerk_informatie_incident,
                'extern_meldingsplichtig' => $incident->extern_meldingsplichtig,
                'beoordeeld_op' => $incident->meldplicht_beoordeeld_op?->toIso8601String(),
                'motivatie' => $incident->meldplicht_motivatie,
            ];
        }

        $meldplicht['verplichtingen'] = $incident->meldingen
            ->sortBy([['grondslag', 'asc'], ['uiterlijk_op', 'asc']])
            ->map(fn (IncidentMelding $m) => [
                'grondslag' => self::enum($m->grondslag, ['avg' => 'AVG', 'cbw' => 'Cyberbeveiligingswet']),
                'fase' => self::enum($m->fase, [$m->fase => $m->label()]),
                'artikel' => $m->artikel(),
                'uiterlijk_op' => $m->uiterlijk_op?->toIso8601String(),
                'gemeld_op' => $m->gemeld_op?->toIso8601String(),
                'gemeld' => $m->isGemeld(),
                'te_laat' => $m->isTeLaat(),
            ])->values()->all();

        return $meldplicht;
    }

    /** @return array<string, mixed> */
    private function afwijking(Afwijking $afwijking, bool $magMuteren): array
    {
        $rij = [
            'id' => $afwijking->id,
            'omschrijving' => $afwijking->omschrijving,
            'bron' => self::enum($afwijking->bron, self::BRON),
            'status' => self::enum($afwijking->status, self::AFWIJKINGSTATUS),
            'incident' => $afwijking->incident === null ? null : [
                'id' => $afwijking->incident->id,
                'titel' => $afwijking->incident->titel,
            ],
            'aangemaakt_op' => $afwijking->created_at?->toIso8601String(),
            'gesloten_op' => $afwijking->gesloten_op?->toIso8601String(),
            'gesloten_door' => $afwijking->gesloten_op ? $afwijking->sluiter?->naam : null,
            'grondoorzaken' => $afwijking->grondoorzaken->map(fn (Grondoorzaak $g) => [
                'omschrijving' => $g->omschrijving,
                'methodiek' => $g->methodiek,
            ])->all(),
            'corrigerende_maatregelen' => $afwijking->maatregelen->map(fn (CorrigerendeMaatregel $m) => [
                'id' => $m->id,
                'omschrijving' => $m->omschrijving,
                'eigenaar' => $m->eigenaar?->naam,
                'deadline' => $m->deadline?->toDateString(),
                'status' => self::enum($m->status, self::MAATREGELSTATUS),
                'voltooid_op' => $m->voltooid_op?->toDateString(),
                'over_termijn' => $m->status !== 'voltooid' && $m->deadline !== null && $m->deadline->isPast(),
                'toetsen' => $m->toetsen->map(fn (Effectiviteitstoets $t) => [
                    'resultaat' => self::enum($t->resultaat, ['effectief' => 'Effectief', 'niet_effectief' => 'Niet effectief']),
                    'uitgevoerd_op' => $t->uitgevoerd_op?->toDateString(),
                    'uitgevoerd_door' => $t->uitvoerder?->naam,
                    'toelichting' => $t->toelichting,
                ])->all(),
            ])->all(),
            'url' => route('afwijkingen.detail', $afwijking),
        ];

        // De eigenaar staat op het scherm alleen in het muteerformulier.
        if ($magMuteren) {
            $rij['eigenaar'] = $afwijking->eigenaar?->naam;
        }

        return $rij;
    }
}
