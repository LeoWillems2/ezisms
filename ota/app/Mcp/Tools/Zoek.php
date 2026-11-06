<?php

namespace App\Mcp\Tools;

use App\Models\Afwijking;
use App\Models\Asset;
use App\Models\Beleidsdocument;
use App\Models\Bevinding;
use App\Models\Bewijsstuk;
use App\Models\Incident;
use App\Models\Leverancier;
use App\Models\Maatregel;
use App\Models\Risico;
use App\Models\Taak;
use App\Support\Assistent\Aanroepcontext;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Vrije tekst over de registers (implementatie/14b §5).
 *
 * Per type dezelfde autorisatiecheck als de route van het lijstscherm, en
 * waar dat scherm per gebruiker filtert, dezelfde `zichtbaar()`-scope van het
 * model. De standaardfilters van de schermen ("alleen actief", "alleen
 * openstaand") volgt de tool níét: dat zijn weergavekeuzes, geen
 * autorisatie. Elk resultaat draagt zijn status, zodat het model zelf kan
 * filteren.
 *
 * Afwijkingen volgen de regel van het detailscherm (eigenaar, of eigenaar van
 * een corrigerende maatregel), want dat is het ruimste wat de UI de gebruiker
 * toont.
 */
#[IsReadOnly]
#[Description('Zoek op vrije tekst in risico\'s, maatregelen (SoA), assets, beleidsdocumenten, incidenten, afwijkingen, leveranciers, auditbevindingen, taken en bewijsstukken. Geeft per treffer type, titel, status en een url naar het ISMS. Doorzoekt titels en omschrijvingen, geen bestandsinhoud.')]
class Zoek extends IsmsTool
{
    private const STANDAARD_LIMIET = 50;

    protected function vereist(): ?array
    {
        return null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'zoekterm' => $schema->string()
                ->description('Tekst om op te zoeken, minstens twee tekens. Hoofdletters maken niet uit.')
                ->required(),
            'types' => $schema->array()
                ->items($schema->string()->enum(array_keys(self::bronnen())))
                ->description('Beperk tot deze types. Leeg of weggelaten: alle types waar de gebruiker bij mag.'),
            'limiet' => $schema->integer()
                ->min(1)
                ->max(self::MAX_RIJEN)
                ->description('Hoeveel treffers per type hooguit. Standaard '.self::STANDAARD_LIMIET.'.'),
        ];
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $invoer = $request->validate([
            'zoekterm' => ['required', 'string', 'min:2', 'max:200'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['string', 'in:'.implode(',', array_keys(self::bronnen()))],
            'limiet' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RIJEN],
        ]);

        $gevraagd = $invoer['types'] ?? [];
        $limiet = (int) ($invoer['limiet'] ?? self::STANDAARD_LIMIET);
        // Eigen escapeteken: SQLite kent bij LIKE geen standaard-escape, MySQL
        // wel (de backslash). Met een expliciete ESCAPE werkt het op beide.
        $patroon = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $invoer['zoekterm']).'%';

        $resultaten = [];
        $perType = [];
        $weggelaten = [];

        foreach (self::bronnen() as $type => $bron) {
            if ($gevraagd !== [] && ! in_array($type, $gevraagd, true)) {
                continue;
            }

            if (! Gate::allows('heeft-niveau', $bron['vereist'])) {
                $weggelaten[] = $bron['label'].' (geen leesrecht)';

                continue;
            }

            $query = ($bron['query'])()->where(function (Builder $q) use ($bron, $patroon) {
                foreach ($bron['kolommen'] as $kolom) {
                    // De kolomnamen komen uit bronnen() hieronder, nooit uit de invoer.
                    $q->orWhereRaw($q->getGrammar()->wrap($kolom)." like ? escape '!'", [$patroon]);
                }
            });

            $totaal = (clone $query)->count();
            $rijen = $query->orderByDesc('id')->limit($limiet)->get();

            $perType[$type] = ['gevonden' => $totaal, 'getoond' => $rijen->count()];

            foreach ($rijen as $rij) {
                $resultaten[] = [
                    'type' => $type,
                    'id' => $rij->getKey(),
                    ...($bron['weergave'])($rij),
                ];
            }
        }

        app(Aanroepcontext::class)->resultaten(count($resultaten));

        return Response::structured([
            'zoekterm' => $invoer['zoekterm'],
            'per_type' => $perType,
            'resultaten' => $resultaten,
            'weggelaten' => $weggelaten,
        ]);
    }

    /**
     * Per type: de check van de route, de query van het scherm, de
     * doorzochte kolommen en hoe een rij eruitziet. Elke `vereist` is
     * overgenomen van de route-middleware in routes/web.php.
     *
     * @return array<string, array{label: string, vereist: array{0: string, 1: string}, query: Closure(): Builder, kolommen: list<string>, weergave: Closure(Model): array<string, mixed>}>
     */
    private static function bronnen(): array
    {
        return [
            'risico' => [
                'label' => 'Risico\'s',
                'vereist' => ['risico-soa', 'lezen'],
                'query' => fn () => Risico::query(),
                'kolommen' => ['titel', 'dreiging', 'kwetsbaarheid'],
                'weergave' => fn (Risico $r) => [
                    'titel' => $r->titel,
                    'status' => self::enum($r->status),
                    'risicoscore' => $r->risicoscore,
                    'url' => route('risicos.detail', $r),
                ],
            ],
            'maatregel' => [
                'label' => 'Maatregelen',
                'vereist' => ['risico-soa', 'lezen'],
                'query' => fn () => Maatregel::query()->with('soaRegel'),
                'kolommen' => ['annex_a_referentie', 'naam', 'omschrijving'],
                // Er is geen detailpagina per maatregel; de SoA bewerkt in een modal.
                'weergave' => fn (Maatregel $m) => [
                    'titel' => trim($m->annex_a_referentie.' '.$m->naam),
                    'status' => self::enum($m->soaRegel?->implementatiestatus),
                    'van_toepassing' => $m->soaRegel?->van_toepassing,
                    'url' => route('soa.index', ['filterThema' => $m->thema]),
                ],
            ],
            'asset' => [
                'label' => 'Assets',
                'vereist' => ['asset-classificatie', 'lezen'],
                'query' => fn () => Asset::query(),
                'kolommen' => ['naam', 'omschrijving', 'type'],
                'weergave' => fn (Asset $a) => [
                    'titel' => $a->naam,
                    'status' => self::enum($a->status),
                    'url' => route('assets.detail', $a),
                ],
            ],
            'beleidsdocument' => [
                'label' => 'Beleidsdocumenten',
                'vereist' => ['beleid-maatregelbeheer', 'uitvoeren'],
                'query' => fn () => Beleidsdocument::query()->zichtbaar(),
                'kolommen' => ['titel', 'omschrijving', 'type'],
                'weergave' => fn (Beleidsdocument $b) => [
                    'titel' => $b->titel,
                    'status' => self::enum($b->status),
                    'url' => route('beleid.detail', $b),
                ],
            ],
            'incident' => [
                'label' => 'Incidenten',
                'vereist' => ['incident-afwijkingenbeheer', 'uitvoeren'],
                'query' => fn () => Incident::query()->zichtbaar(),
                'kolommen' => ['titel', 'omschrijving'],
                'weergave' => fn (Incident $i) => [
                    'titel' => $i->titel,
                    'status' => self::enum($i->status),
                    'url' => route('incidenten.detail', $i),
                ],
            ],
            'afwijking' => [
                'label' => 'Afwijkingen',
                'vereist' => ['incident-afwijkingenbeheer', 'uitvoeren'],
                'query' => fn () => Afwijking::query()->zichtbaar(),
                'kolommen' => ['omschrijving', 'bron'],
                'weergave' => fn (Afwijking $a) => [
                    'titel' => Str::limit((string) $a->omschrijving, 120),
                    'status' => self::enum($a->status),
                    'url' => route('afwijkingen.detail', $a),
                ],
            ],
            'leverancier' => [
                'label' => 'Leveranciers',
                'vereist' => ['leveranciers-derdenrisico', 'lezen'],
                'query' => fn () => Leverancier::query(),
                'kolommen' => ['naam'],
                'weergave' => fn (Leverancier $l) => [
                    'titel' => $l->naam,
                    'status' => self::enum($l->status),
                    'risiconiveau' => $l->risiconiveau,
                    'url' => route('leveranciers.detail', $l),
                ],
            ],
            'bevinding' => [
                'label' => 'Auditbevindingen',
                'vereist' => ['auditmanagement', 'lezen'],
                'query' => fn () => Bevinding::query(),
                'kolommen' => ['omschrijving', 'afhandelingsnotitie', 'type'],
                // Geen detailpagina per bevinding; de auditronde toont ze.
                'weergave' => fn (Bevinding $b) => [
                    'titel' => Str::limit((string) $b->omschrijving, 120),
                    'status' => self::enum($b->status),
                    'soort' => self::enum($b->type),
                    'url' => $b->auditronde_id !== null
                        ? route('audits.ronde', $b->auditronde_id)
                        : route('audits.bevindingen'),
                ],
            ],
            'taak' => [
                'label' => 'Taken',
                'vereist' => ['taken-workflow-engine', 'uitvoeren'],
                'query' => fn () => Taak::query()->zichtbaar(),
                'kolommen' => ['titel', 'omschrijving'],
                'weergave' => fn (Taak $t) => [
                    'titel' => $t->titel,
                    'status' => self::enum($t->status),
                    'deadline' => $t->deadline?->toDateString(),
                    'url' => route('taken.index'),
                ],
            ],
            'bewijsstuk' => [
                'label' => 'Bewijsstukken',
                'vereist' => ['bewijsrepository-audit-trail', 'uitvoeren'],
                'query' => fn () => Bewijsstuk::query()->zichtbaar(),
                'kolommen' => ['naam', 'omschrijving', 'bestandsnaam'],
                'weergave' => fn (Bewijsstuk $b) => [
                    'titel' => $b->naam,
                    'status' => self::enum($b->status),
                    'bestandsnaam' => $b->bestandsnaam,
                    'url' => route('bewijsstukken.download', $b),
                ],
            ],
        ];
    }
}
