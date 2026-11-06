<?php

namespace App\Mcp\Tools;

use App\Models\Risico;
use App\Models\Risicobehandeling;
use App\Models\SoaRegel;
use App\Support\Assistent\Aanroepcontext;
use App\Support\Beoordelingsschaal;
use App\Support\Risicoselectie;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Het risicoregister (implementatie/14b §5).
 *
 * De rijen en de volgorde komen uit {@see Risicoselectie}, dezelfde query als
 * `RisicosOverzicht`. De velden zijn die van het register plus het
 * risicodetail: kans, impact, behandelingen en restrisico. Beide schermen
 * staan achter hetzelfde recht en kennen geen record-scoping.
 */
#[IsReadOnly]
#[Description('Het risicoregister: per risico kans, impact, score, of het boven de acceptatiedrempel ligt, status, eigenaar, gekoppeld asset, de behandelingen met restrisico en de maatregelen die ze behandelen. Gebruik dit voor vragen als "welke risico\'s liggen boven de drempel", "wat is het restrisico van R-12" of "welke risico\'s horen bij dit asset".')]
class Risicos extends IsmsTool
{
    private const STANDAARD_LIMIET = 50;

    protected function vereist(): ?array
    {
        // routes/web.php: /risicos en /risicos/{risico} staan achter heeft-niveau,'risico-soa','lezen'.
        return ['risico-soa', 'lezen'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)
                ->description('Eén risico op id (R-12 is id 12). Laat weg voor een lijst.'),
            'status' => $schema->string()
                ->enum(array_keys(Risicoselectie::STATUS_LABELS))
                ->description('Alleen risico\'s met deze status.'),
            'boven_drempel' => $schema->boolean()
                ->description('Alleen risico\'s waarvan de score boven de acceptatiedrempel ligt.'),
            'eigenaar_id' => $schema->integer()->min(1)
                ->description('Alleen risico\'s van deze risico-eigenaar (gebruikers-id).'),
            'asset_id' => $schema->integer()->min(1)
                ->description('Alleen risico\'s die aan dit asset gekoppeld zijn.'),
            'limiet' => $schema->integer()->min(1)->max(self::MAX_RIJEN)
                ->description('Hoeveel risico\'s per pagina. Standaard '.self::STANDAARD_LIMIET.'.'),
            'pagina' => $schema->integer()->min(1)->description('Paginanummer, vanaf 1.'),
        ];
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $invoer = $request->validate([
            'id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', 'in:'.implode(',', array_keys(Risicoselectie::STATUS_LABELS))],
            'boven_drempel' => ['sometimes', 'boolean'],
            'eigenaar_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'asset_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'limiet' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RIJEN],
            'pagina' => ['sometimes', 'integer', 'min:1'],
        ]);

        $limiet = (int) ($invoer['limiet'] ?? self::STANDAARD_LIMIET);
        $pagina = (int) ($invoer['pagina'] ?? 1);

        $query = Risicoselectie::query(
            (string) ($invoer['status'] ?? ''),
            (bool) ($invoer['boven_drempel'] ?? false),
        )
            ->with(['asset', 'behandelingen' => fn ($q) => $q->orderBy('id'), 'behandelingen.soaRegels.maatregel'])
            ->when(isset($invoer['id']), fn ($q) => $q->whereKey($invoer['id']))
            ->when(isset($invoer['eigenaar_id']), fn ($q) => $q->where('risico_eigenaar_id', $invoer['eigenaar_id']))
            ->when(isset($invoer['asset_id']), fn ($q) => $q->where('gekoppeld_asset_id', $invoer['asset_id']));

        $totaal = (clone $query)->count();
        $rijen = $query->forPage($pagina, $limiet)->get();

        app(Aanroepcontext::class)->resultaten($rijen->count());

        if (isset($invoer['id']) && $totaal === 0) {
            return Response::error("Geen risico met id {$invoer['id']}.");
        }

        return Response::structured([
            'acceptatiedrempel' => Risico::drempelwaarde(),
            'waarschuwingsdrempel' => Risico::waarschuwingsdrempel(),
            'totaal' => $totaal,
            'pagina' => $pagina,
            'volgende_pagina' => $totaal > $pagina * $limiet ? $pagina + 1 : null,
            'risicos' => $rijen->map(fn (Risico $r) => $this->rij($r))->all(),
            'url' => route('risicos.index'),
        ]);
    }

    /** @return array<string, mixed> */
    private function rij(Risico $risico): array
    {
        $behandelingen = $risico->behandelingen;
        // Het detailscherm opent de laatst aangemaakte behandeling (mount).
        $laatste = $behandelingen->sortByDesc('id')->first();

        return [
            'id' => $risico->id,
            'referentie' => $risico->referentie(),
            'titel' => $risico->titel,
            'dreiging' => $risico->dreiging,
            'kwetsbaarheid' => $risico->kwetsbaarheid,
            'status' => self::enum($risico->status, Risicoselectie::STATUS_LABELS),
            'kans' => $risico->kans_niveau === null ? null : [
                'niveau' => $risico->kans_niveau,
                'label' => Beoordelingsschaal::naam('kans', $risico->kans_niveau),
            ],
            'impact' => $risico->impact_niveau === null ? null : [
                'niveau' => $risico->impact_niveau,
                'label' => Beoordelingsschaal::naam('impact', $risico->impact_niveau),
            ],
            'risicoscore' => $risico->risicoscore,
            'boven_drempel' => $risico->risicoscore === null ? null : $risico->boventDrempel(),
            'beoordeeld_onder_oud_criterium' => $risico->beoordeeldOnderOudCriterium(),
            'volgende_beoordeling_gepland' => $risico->volgende_beoordeling_gepland?->toDateString(),
            'herbeoordeling_verstreken' => $risico->herbeoordelingVerstreken(),
            'eigenaar' => $risico->eigenaar === null ? null : [
                'id' => $risico->eigenaar->id,
                'naam' => $risico->eigenaar->naam,
                'actief' => $risico->eigenaar->isActief(),
            ],
            'asset' => $risico->asset === null ? null : [
                'id' => $risico->asset->id,
                'naam' => $risico->asset->naam,
                'url' => route('assets.detail', $risico->asset),
            ],
            'restrisico' => $laatste?->restrisico_score,
            'behandelingen' => $behandelingen->map(fn (Risicobehandeling $b) => [
                'behandeloptie' => self::enum($b->behandeloptie),
                'restrisico_score' => $b->restrisico_score,
                'geaccepteerd_door' => $b->geaccepteerd_door,
                'geaccepteerd_op' => $b->geaccepteerd_op?->toDateString(),
                'maatregelen' => $b->soaRegels
                    ->map(fn (SoaRegel $r) => $r->maatregel?->annex_a_referentie)
                    ->filter()->values()->all(),
            ])->values()->all(),
            'url' => route('risicos.detail', $risico),
        ];
    }
}
