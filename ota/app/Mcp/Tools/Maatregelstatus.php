<?php

namespace App\Mcp\Tools;

use App\Models\Beleidsdocument;
use App\Models\BewijsKoppeling;
use App\Models\Maatregel;
use App\Models\OverheidsmaatregelBeoordeling;
use App\Models\Risicobehandeling;
use App\Support\Assistent\Aanroepcontext;
use App\Support\Maatregelverdeling;
use App\Support\Normprofiel;
use App\Support\Soaselectie;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * De SoA per maatregel (implementatie/14b §5).
 *
 * De rijen komen uit {@see Soaselectie}, dezelfde query als `SoaOverzicht`:
 * daarmee telt ook hier alleen actief beleid als onderbouwing. De motivatie
 * staat erbij omdat de schermkopie van de SoA hem aan iedere lezer geeft.
 *
 * Het gekoppelde bewijs volgt het bewijspaneel onder de maatregel: dat toont
 * alles wat aan de SoA-regel hangt, zonder de record-scope van blok 6, aan
 * wie de SoA mag zien. Er staan alleen metadata in; de inhoud loopt via de
 * tool `bewijs` en zijn resource.
 */
#[IsReadOnly]
#[Description('Stand van de Statement of Applicability per beheersmaatregel: van toepassing ja/nee met motivatie, implementatiestatus, onderbouwend actief beleid, gekoppeld bewijs met ouderdom, gekoppelde risico\'s en restrisico. Gebruik dit voor vragen als "hoe staat 8.13 ervoor", "welke maatregelen zijn nog niet geïmplementeerd" of "waar ontbreekt bewijs". Geeft geen normtekst.')]
class Maatregelstatus extends IsmsTool
{
    private const STANDAARD_LIMIET = 50;

    /** Hoeveel bewijsstukken per maatregel hooguit in de lijst staan; het aantal staat er altijd bij. */
    private const BEWIJS_PER_MAATREGEL = 20;

    protected function vereist(): ?array
    {
        // routes/web.php: /soa staat achter heeft-niveau,'risico-soa','lezen'.
        return ['risico-soa', 'lezen'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'nummer' => $schema->string()
                ->description('Eén maatregel op nummer, bijvoorbeeld "8.13" (een voorloop "A." mag). Laat weg voor een lijst.'),
            'thema' => $schema->string()
                ->enum(array_keys(Maatregelverdeling::THEMA_LABELS))
                ->description('Alleen maatregelen uit dit thema.'),
            'implementatiestatus' => $schema->string()
                ->enum(Maatregelverdeling::statussen())
                ->description('Alleen maatregelen met deze implementatiestatus.'),
            'alleen_onbeslist' => $schema->boolean()
                ->description('Alleen maatregelen waarvan nog niet is besloten of ze van toepassing zijn.'),
            'limiet' => $schema->integer()->min(1)->max(self::MAX_RIJEN)
                ->description('Hoeveel maatregelen per pagina. Standaard '.self::STANDAARD_LIMIET.'.'),
            'pagina' => $schema->integer()->min(1)->description('Paginanummer, vanaf 1.'),
        ];
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $invoer = $request->validate([
            'nummer' => ['sometimes', 'nullable', 'string', 'max:20'],
            'thema' => ['sometimes', 'nullable', 'in:'.implode(',', array_keys(Maatregelverdeling::THEMA_LABELS))],
            'implementatiestatus' => ['sometimes', 'nullable', 'in:'.implode(',', Maatregelverdeling::statussen())],
            'alleen_onbeslist' => ['sometimes', 'boolean'],
            'limiet' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RIJEN],
            'pagina' => ['sometimes', 'integer', 'min:1'],
        ]);

        $limiet = (int) ($invoer['limiet'] ?? self::STANDAARD_LIMIET);
        $pagina = (int) ($invoer['pagina'] ?? 1);
        $nummer = isset($invoer['nummer']) ? preg_replace('/^A\.?\s*/i', '', trim($invoer['nummer'])) : '';

        $query = Soaselectie::query(
            (string) ($invoer['thema'] ?? ''),
            (string) ($invoer['implementatiestatus'] ?? ''),
            (bool) ($invoer['alleen_onbeslist'] ?? false),
        )
            // Bovenop wat het scherm laadt: de risico's achter de behandelingen,
            // voor titel en url. Het scherm toont die op het risicodetail.
            ->with('soaRegel.risicobehandelingen.risico')
            ->when($nummer !== '', fn ($q) => $q->where('annex_a_referentie', $nummer));

        $alle = Soaselectie::sorteer($query->get());
        $rijen = $alle->slice(($pagina - 1) * $limiet, $limiet)->values();
        $bewijs = $this->bewijsPerRegel($rijen);

        app(Aanroepcontext::class)->resultaten($rijen->count());

        if ($nummer !== '' && $alle->isEmpty()) {
            return Response::error("Geen maatregel met nummer {$nummer} in deze SoA.");
        }

        return Response::structured([
            'normprofiel' => Normprofiel::label('naam'),
            'totaal' => $alle->count(),
            'pagina' => $pagina,
            'volgende_pagina' => $alle->count() > $pagina * $limiet ? $pagina + 1 : null,
            'maatregelen' => $rijen->map(fn (Maatregel $m) => $this->rij($m, $bewijs))->all(),
            'url' => route('soa.index'),
        ]);
    }

    /**
     * @param  array<int, Collection<int, BewijsKoppeling>>  $bewijs
     * @return array<string, mixed>
     */
    private function rij(Maatregel $maatregel, array $bewijs): array
    {
        $regel = $maatregel->soaRegel;

        $rij = [
            'nummer' => $maatregel->annex_a_referentie,
            'naam' => $maatregel->naam,
            'thema' => self::enum($maatregel->thema, Maatregelverdeling::THEMA_LABELS),
            // Er is geen detailpagina per maatregel; de SoA bewerkt in een modal.
            'url' => route('soa.index', ['filterThema' => $maatregel->thema]),
        ];

        if ($regel === null) {
            return [...$rij, 'soa_regel' => null];
        }

        $koppelingen = $bewijs[$regel->id] ?? collect();

        return [
            ...$rij,
            'van_toepassing' => $regel->van_toepassing,
            'motivatie' => $regel->motivatie,
            'implementatiestatus' => self::enum($regel->implementatiestatus, Maatregelverdeling::STATUS_LABELS),
            'laatst_beoordeeld_op' => $regel->laatst_beoordeeld_op?->toDateString(),
            'beleidreferentie' => $regel->beleidreferentie,
            'procesreferentie' => $regel->procesreferentie,
            // Alleen actief beleid, zoals de SoA (de relatie is zo ingeladen).
            'beleid' => $regel->beleidsdocumenten->map(fn (Beleidsdocument $b) => [
                'id' => $b->id,
                'titel' => $b->titel,
                'url' => route('beleid.detail', $b),
            ])->values()->all(),
            'mist_beleid' => $regel->mistBeleid(),
            'restrisico' => [
                'piek' => $regel->piekRestrisico(),
                'aantal_risicos' => $regel->aantalGekoppeldeRisicos(),
            ],
            'risicos' => $regel->risicobehandelingen
                ->map(fn (Risicobehandeling $b) => [
                    'id' => $b->risico_id,
                    'referentie' => $b->risico?->referentie(),
                    'titel' => $b->risico?->titel,
                    'behandeloptie' => self::enum($b->behandeloptie),
                    'restrisico_score' => $b->restrisico_score,
                    'url' => $b->risico ? route('risicos.detail', $b->risico) : null,
                ])->values()->all(),
            'bewijs' => $this->bewijsSamenvatting($koppelingen),
            ...(Normprofiel::heeft('overheidsmaatregelen')
                ? ['overheidsmaatregelen' => $regel->overheidsmaatregelBeoordelingen
                    ->map(fn (OverheidsmaatregelBeoordeling $b) => [
                        // Het nummer en de eigen beoordeling; de BIO-tekst niet (14b §0).
                        'nummer' => $b->overheidsmaatregel?->nummer,
                        'status' => self::enum($b->status, OverheidsmaatregelBeoordeling::STATUS_LABELS),
                        'motivatie' => $b->motivatie,
                        'laatst_beoordeeld_op' => $b->laatst_beoordeeld_op?->toDateString(),
                        'aantal_bewijsstukken' => $b->bewijsKoppelingen->count(),
                        'mist_bewijs' => $b->mistBewijs(),
                    ])->values()->all()]
                : []),
        ];
    }

    /**
     * Alle koppelingen aan de getoonde SoA-regels, en in een BIO-installatie aan
     * de verplichtingen eronder, in één query. Gesleuteld op SoA-regel-id.
     *
     * Dezelfde verzameling als `BewijsPaneel::gekoppeldeQuery()`: alles wat aan
     * de entiteit hangt, zonder de record-scope van blok 6.
     *
     * @param  Collection<int, Maatregel>  $maatregelen
     * @return array<int, Collection<int, BewijsKoppeling>>
     */
    private function bewijsPerRegel(Collection $maatregelen): array
    {
        $regels = $maatregelen->pluck('soaRegel')->filter();

        if ($regels->isEmpty()) {
            return [];
        }

        // Beoordeling-id => SoA-regel-id. Een lus en geen flatMap: die laatste
        // gooit de sleutels weg, en de sleutel is hier juist de inhoud.
        $beoordelingNaarRegel = [];
        if (Normprofiel::heeft('overheidsmaatregelen')) {
            foreach ($regels as $regel) {
                foreach ($regel->overheidsmaatregelBeoordelingen as $beoordeling) {
                    $beoordelingNaarRegel[$beoordeling->id] = $regel->id;
                }
            }
        }

        $koppelingen = BewijsKoppeling::query()
            ->with('bewijsstuk')
            ->where(fn ($q) => $q
                ->where(fn ($s) => $s->where('entiteit_type', 'soa_regel')->whereIn('entiteit_id', $regels->pluck('id')))
                ->when($beoordelingNaarRegel !== [], fn ($s) => $s->orWhere(fn ($o) => $o
                    ->where('entiteit_type', 'overheidsmaatregel_beoordeling')
                    ->whereIn('entiteit_id', array_keys($beoordelingNaarRegel)))))
            ->get();

        return $koppelingen
            ->filter(fn (BewijsKoppeling $k) => $k->bewijsstuk !== null)
            ->groupBy(fn (BewijsKoppeling $k) => $k->entiteit_type === 'soa_regel'
                ? $k->entiteit_id
                : $beoordelingNaarRegel[$k->entiteit_id])
            ->all();
    }

    /**
     * @param  Collection<int, BewijsKoppeling>  $koppelingen
     * @return array<string, mixed>
     */
    private function bewijsSamenvatting(Collection $koppelingen): array
    {
        $stukken = $koppelingen
            ->unique('bewijsstuk_id')
            ->sortByDesc(fn (BewijsKoppeling $k) => $k->bewijsstuk->geupload_op)
            ->values();
        $actief = $stukken->filter(fn (BewijsKoppeling $k) => $k->bewijsstuk->status === 'actief');
        $nieuwste = $actief->first()?->bewijsstuk->geupload_op;

        return [
            'aantal' => $stukken->count(),
            'aantal_actief' => $actief->count(),
            'nieuwste_actief_op' => $nieuwste?->toIso8601String(),
            'leeftijd_nieuwste_dagen' => $nieuwste ? (int) $nieuwste->diffInDays(now()) : null,
            'stukken' => $stukken->take(self::BEWIJS_PER_MAATREGEL)->map(fn (BewijsKoppeling $k) => [
                'id' => $k->bewijsstuk->id,
                'naam' => $k->bewijsstuk->naam,
                'status' => self::enum($k->bewijsstuk->status),
                'geupload_op' => $k->bewijsstuk->geupload_op?->toIso8601String(),
                'gekoppeld_aan' => $k->entiteit_type === 'soa_regel' ? 'maatregel' : 'overheidsmaatregel',
                'resource_uri' => 'isms://bewijs/'.$k->bewijsstuk->id,
                'url' => route('bewijsstukken.download', $k->bewijsstuk),
            ])->all(),
        ];
    }
}
