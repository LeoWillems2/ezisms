<?php

namespace App\Mcp\Tools;

use App\Models\Afwijking;
use App\Models\Beleidsversie;
use App\Models\CorrigerendeMaatregel;
use App\Models\Taak;
use App\Models\Verbeteractie;
use App\Support\Assistent\Aanroepcontext;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Alles wat openstaat met een eigenaar en een termijn (implementatie/14b §5).
 *
 * Per soort de check van de route van het scherm waar hij staat, en waar dat
 * scherm per gebruiker filtert dezelfde scope:
 *
 * - taken: /taken (taken-workflow-engine `uitvoeren`) met `Taak::zichtbaar()`;
 * - corrigerende maatregelen: afwijkingen.detail (incident-afwijkingenbeheer
 *   `uitvoeren`) bij een afwijking uit `Afwijking::zichtbaar()`;
 * - verbeteracties: /management-review (`lezen`), zonder record-scoping;
 * - beleidsherzieningen: beleid.detail (beleid-maatregelbeheer `uitvoeren`)
 *   bij een document uit `Beleidsdocument::zichtbaar()`.
 */
#[IsReadOnly]
#[Description('Open en verlopen acties met eigenaar en termijn: taken, corrigerende maatregelen (bij afwijkingen), verbeteracties (uit de management review) en geplande beleidsherzieningen. Per rij soort, titel, eigenaar, deadline, status en of de termijn verstreken is. Gebruik dit voor "wat ligt er nog open", "wat is over tijd" of "wat staat er op naam van X". Let op: verbeteracties en beleidsherzieningen krijgen ook een herinneringstaak in de takenlijst; een taak met taaksoort wijst daarop.')]
class OpenActies extends IsmsTool
{
    private const STANDAARD_LIMIET = 50;

    /** Beleidsherzieningen liggen vaak een jaar vooruit; standaard alleen wat binnen deze termijn valt. */
    private const HERZIENING_HORIZON_DAGEN = 90;

    protected function vereist(): ?array
    {
        return null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'soorten' => $schema->array()
                ->items($schema->string()->enum(array_keys(self::soorten())))
                ->description('Beperk tot deze soorten. Weggelaten: alle soorten waar de gebruiker bij mag.'),
            'alleen_over_termijn' => $schema->boolean()
                ->description('Alleen acties waarvan de deadline verstreken is.'),
            'alleen_van_mij' => $schema->boolean()
                ->description('Alleen acties op naam van de ingelogde gebruiker.'),
            'binnen_dagen' => $schema->integer()->min(0)->max(3660)
                ->description('Alleen acties met een deadline binnen zoveel dagen vanaf vandaag (verlopen telt mee). Beleidsherzieningen standaard binnen '.self::HERZIENING_HORIZON_DAGEN.' dagen.'),
            'limiet' => $schema->integer()->min(1)->max(self::MAX_RIJEN)
                ->description('Hoeveel rijen per soort hooguit, vroegste deadline eerst. Standaard '.self::STANDAARD_LIMIET.'.'),
        ];
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $invoer = $request->validate([
            'soorten' => ['sometimes', 'array'],
            'soorten.*' => ['string', 'in:'.implode(',', array_keys(self::soorten()))],
            'alleen_over_termijn' => ['sometimes', 'boolean'],
            'alleen_van_mij' => ['sometimes', 'boolean'],
            'binnen_dagen' => ['sometimes', 'integer', 'min:0', 'max:3660'],
            'limiet' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RIJEN],
        ]);

        $gevraagd = $invoer['soorten'] ?? [];
        $limiet = (int) ($invoer['limiet'] ?? self::STANDAARD_LIMIET);
        $filters = [
            'over_termijn' => (bool) ($invoer['alleen_over_termijn'] ?? false),
            'van_mij' => (bool) ($invoer['alleen_van_mij'] ?? false),
            'binnen_dagen' => isset($invoer['binnen_dagen']) ? (int) $invoer['binnen_dagen'] : null,
        ];

        $acties = [];
        $perSoort = [];
        $weggelaten = [];

        foreach (self::soorten() as $soort => $bron) {
            if ($gevraagd !== [] && ! in_array($soort, $gevraagd, true)) {
                continue;
            }

            if (! Gate::allows('heeft-niveau', $bron['vereist'])) {
                $weggelaten[] = $bron['label'].' (geen toegang tot '.$bron['scherm'].')';

                continue;
            }

            $query = ($bron['query'])($filters);
            $totaal = (clone $query)->count();
            $rijen = $query->limit($limiet)->get();

            $perSoort[$soort] = ['gevonden' => $totaal, 'getoond' => $rijen->count()];

            foreach ($rijen as $rij) {
                $acties[] = ['soort' => $soort, 'id' => $rij->getKey(), ...($bron['weergave'])($rij)];
            }
        }

        // Over alle soorten heen op deadline; zonder deadline achteraan.
        usort($acties, fn (array $a, array $b) => [$a['deadline'] === null, $a['deadline']]
            <=> [$b['deadline'] === null, $b['deadline']]);

        app(Aanroepcontext::class)->resultaten(count($acties));

        return Response::structured([
            'peildatum' => now()->toIso8601String(),
            'totaal' => array_sum(array_column($perSoort, 'gevonden')),
            'per_soort' => $perSoort,
            'acties' => $acties,
            'weggelaten' => $weggelaten,
        ]);
    }

    /**
     * Past de gedeelde filters toe op een deadlinekolom en de eigenaar.
     *
     * @param  array{over_termijn: bool, van_mij: bool, binnen_dagen: int|null}  $filters
     * @param  string|Closure(Builder): Builder  $eigenaar  de eigenaarkolom, of een eigen filter voor "van mij"
     */
    private static function filter(Builder $query, array $filters, string $deadline, string|Closure $eigenaar, ?int $standaardHorizon = null): Builder
    {
        $horizon = $filters['binnen_dagen'] ?? $standaardHorizon;

        return $query
            // Een datumkolom die vandaag is, telt als verstreken: dezelfde
            // `isPast()` als de schermen (middernacht ligt achter ons).
            ->when($filters['over_termijn'], fn (Builder $q) => $q->whereDate($deadline, '<=', today()))
            ->when($horizon !== null, fn (Builder $q) => $q->whereDate($deadline, '<=', today()->addDays($horizon)))
            ->when($filters['van_mij'], fn (Builder $q) => is_string($eigenaar)
                ? $q->where($eigenaar, auth()->id())
                : $eigenaar($q))
            ->orderByRaw($query->getGrammar()->wrap($deadline).' is null')
            ->orderBy($deadline);
    }

    private static function datum(?CarbonInterface $datum): ?string
    {
        return $datum?->toDateString();
    }

    /**
     * Per soort: de check van de route, de query van het scherm en hoe een rij
     * eruitziet. Elke `vereist` is overgenomen van de route-middleware.
     *
     * @return array<string, array{label: string, scherm: string, vereist: array{0: string, 1: string}, query: Closure(array): Builder, weergave: Closure(mixed): array<string, mixed>}>
     */
    private static function soorten(): array
    {
        return [
            'taak' => [
                'label' => 'Taken',
                'scherm' => 'Taken & Workflow',
                'vereist' => ['taken-workflow-engine', 'uitvoeren'],
                'query' => fn (array $f) => self::filter(
                    Taak::query()->zichtbaar()->whereIn('status', Taak::OPENSTAAND)->with(['eigenaar:id,naam', 'entiteit']),
                    $f, 'deadline', 'eigenaar_id',
                ),
                'weergave' => fn (Taak $t) => [
                    'titel' => $t->titel,
                    'eigenaar' => $t->eigenaar?->naam,
                    'deadline' => self::datum($t->deadline),
                    'status' => self::enum($t->status),
                    'over_termijn' => $t->isFeitelijkVerlopen(),
                    'gekoppeld' => $t->gekoppeldOmschrijving(),
                    'taaksoort' => $t->soort,
                    'url' => route('taken.index'),
                ],
            ],
            'corrigerende_maatregel' => [
                'label' => 'Corrigerende maatregelen',
                'scherm' => 'Incident- & Afwijkingenbeheer',
                'vereist' => ['incident-afwijkingenbeheer', 'uitvoeren'],
                'query' => fn (array $f) => self::filter(
                    CorrigerendeMaatregel::query()
                        ->where('status', '!=', 'voltooid')
                        ->whereHas('afwijking', fn (Builder $a) => $a->zichtbaar()->whereNull('gesloten_op'))
                        ->with(['eigenaar:id,naam', 'afwijking']),
                    $f, 'deadline', 'eigenaar_id',
                ),
                'weergave' => fn (CorrigerendeMaatregel $m) => [
                    'titel' => $m->omschrijving,
                    'eigenaar' => $m->eigenaar?->naam,
                    'deadline' => self::datum($m->deadline),
                    'status' => self::enum($m->status, ['open' => 'Open', 'in_uitvoering' => 'In uitvoering']),
                    'over_termijn' => $m->deadline !== null && $m->deadline->isPast(),
                    'gekoppeld' => 'Afwijking: '.$m->afwijking->auditOmschrijving(),
                    'url' => route('afwijkingen.detail', $m->afwijking_id),
                ],
            ],
            'verbeteractie' => [
                'label' => 'Verbeteracties',
                'scherm' => 'Management Review & Verbetercyclus',
                'vereist' => ['management-review-verbetercyclus', 'lezen'],
                'query' => fn (array $f) => self::filter(
                    Verbeteractie::query()->where('status', 'open')->with(['eigenaar:id,naam', 'besluit']),
                    $f, 'deadline', 'eigenaar_id',
                ),
                'weergave' => fn (Verbeteractie $v) => [
                    'titel' => $v->omschrijving,
                    'eigenaar' => $v->eigenaar?->naam,
                    'deadline' => self::datum($v->deadline),
                    'status' => self::enum($v->status, ['open' => 'Open']),
                    // Dezelfde regel als de kleur in het reviewsessiescherm.
                    'over_termijn' => $v->deadline !== null && $v->deadline->isPast(),
                    'gekoppeld' => $v->besluit ? 'Besluit: '.$v->besluit->omschrijving : null,
                    'url' => $v->besluit
                        ? route('management-review.detail', $v->besluit->reviewsessie_id)
                        : route('management-review.index'),
                ],
            ],
            'beleidsherziening' => [
                'label' => 'Beleidsherzieningen',
                'scherm' => 'Beleid & Maatregelbeheer',
                'vereist' => ['beleid-maatregelbeheer', 'uitvoeren'],
                // De geplande herziening van de geldende versie, zoals het
                // detailscherm hem toont. Eigenaar is de documenteigenaar.
                'query' => fn (array $f) => self::filter(
                    Beleidsversie::query()
                        ->where('beleidsversies.status', 'actief')
                        ->whereNotNull('volgende_herziening_gepland')
                        ->whereHas('document', fn (Builder $d) => $d->zichtbaar())
                        ->with(['document.eigenaar:id,naam']),
                    $f, 'volgende_herziening_gepland',
                    // De eigenaar zit op het document, niet op de versie.
                    fn (Builder $q) => $q->whereHas('document', fn (Builder $d) => $d->where('eigenaar_id', auth()->id())),
                    self::HERZIENING_HORIZON_DAGEN,
                ),
                'weergave' => fn (Beleidsversie $v) => [
                    'titel' => 'Herziening '.$v->document->titel.' (v'.$v->versienummer.')',
                    'eigenaar' => $v->document->eigenaar?->naam,
                    'deadline' => self::datum($v->volgende_herziening_gepland),
                    'status' => self::enum('gepland', ['gepland' => 'Gepland']),
                    'over_termijn' => $v->herzieningVerstreken(),
                    'gekoppeld' => 'Beleid: '.$v->document->titel,
                    'url' => route('beleid.detail', $v->beleidsdocument_id),
                ],
            ],
        ];
    }
}
