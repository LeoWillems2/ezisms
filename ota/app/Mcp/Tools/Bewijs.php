<?php

namespace App\Mcp\Tools;

use App\Models\BewijsKoppeling;
use App\Models\Bewijsstuk;
use App\Models\Maatregel;
use App\Support\Assistent\Aanroepcontext;
use App\Support\Koppelbaar;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Metadata van bewijsstukken (implementatie/14b §5).
 *
 * Dezelfde scope als `BewijsstukkenOverzicht`: `zichtbaar()`, dus wie niet
 * alles mag zien, krijgt alleen zijn eigen uploads. De koppelingen staan erbij
 * met dezelfde omschrijving als de kolom "Gekoppeld aan".
 *
 * Het statusfilter van het scherm staat standaard op "actief"; deze tool niet.
 * Dat is een weergavekeuze, en elk stuk draagt zijn status (zoals bij `zoek`).
 *
 * De inhoud zit hier niet in: elk stuk heeft een `resource_uri` die de
 * resource `isms://bewijs/{id}` uitlevert, met de controle van de download.
 */
#[IsReadOnly]
#[Description('Metadata van bewijsstukken: naam, bestand, type, grootte, status, uploaddatum, ouderdom in dagen, bewaartermijn en waaraan het stuk gekoppeld is. Filter op maatregelnummer, ouderdom, status of ongekoppeld. Gebruik dit voor vragen als "welk bewijs is er voor 5.15", "welk bewijs is ouder dan een jaar" of "welke stukken hangen nergens aan". Geeft geen bestandsinhoud; haal die op via de resource_uri.')]
class Bewijs extends IsmsTool
{
    private const STANDAARD_LIMIET = 50;

    private const STATUSSEN = ['actief', 'gearchiveerd'];

    protected function vereist(): ?array
    {
        // routes/web.php: /bewijsstukken staat achter heeft-niveau,'bewijsrepository-audit-trail','uitvoeren'.
        return ['bewijsrepository-audit-trail', 'uitvoeren'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->description('Eén bewijsstuk op id.'),
            'maatregel' => $schema->string()
                ->description('Alleen bewijs dat aan deze SoA-maatregel hangt (of aan een overheidsmaatregel eronder), bijvoorbeeld "5.15".'),
            'ouder_dan_dagen' => $schema->integer()->min(0)
                ->description('Alleen stukken die meer dan zoveel dagen geleden zijn geüpload.'),
            'status' => $schema->string()->enum(self::STATUSSEN)
                ->description('Alleen stukken met deze status. Weggelaten: beide.'),
            'ongekoppeld' => $schema->boolean()
                ->description('Alleen stukken die nergens aan gekoppeld zijn (zonder bewijswaarde).'),
            'limiet' => $schema->integer()->min(1)->max(self::MAX_RIJEN)
                ->description('Hoeveel stukken per pagina. Standaard '.self::STANDAARD_LIMIET.'.'),
            'pagina' => $schema->integer()->min(1)->description('Paginanummer, vanaf 1.'),
        ];
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $invoer = $request->validate([
            'id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'maatregel' => ['sometimes', 'nullable', 'string', 'max:20'],
            'ouder_dan_dagen' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'nullable', 'in:'.implode(',', self::STATUSSEN)],
            'ongekoppeld' => ['sometimes', 'boolean'],
            'limiet' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RIJEN],
            'pagina' => ['sometimes', 'integer', 'min:1'],
        ]);

        $limiet = (int) ($invoer['limiet'] ?? self::STANDAARD_LIMIET);
        $pagina = (int) ($invoer['pagina'] ?? 1);
        $nummer = isset($invoer['maatregel']) ? preg_replace('/^A\.?\s*/i', '', trim($invoer['maatregel'])) : '';

        if ($nummer !== '' && ! Maatregel::where('annex_a_referentie', $nummer)->exists()) {
            return Response::error("Geen maatregel met nummer {$nummer}.");
        }

        $query = Bewijsstuk::query()
            ->with(['uploader', 'koppelingen.entiteit'])
            // Record-scoped, zoals het scherm: `uitvoeren` betekent "eigen bewijs
            // uploaden", niet "alle bewijsstukken inzien" (implementatie/06 §8).
            ->zichtbaar()
            ->when(isset($invoer['id']), fn ($q) => $q->whereKey($invoer['id']))
            ->when(isset($invoer['status']), fn ($q) => $q->where('status', $invoer['status']))
            ->when($invoer['ongekoppeld'] ?? false, fn ($q) => $q->whereDoesntHave('koppelingen'))
            ->when(isset($invoer['ouder_dan_dagen']), fn ($q) => $q
                ->where('geupload_op', '<', now()->subDays((int) $invoer['ouder_dan_dagen'])))
            ->when($nummer !== '', fn ($q) => $q->whereHas('koppelingen', fn (Builder $k) => $k
                ->where(fn ($s) => $s
                    ->where('entiteit_type', 'soa_regel')
                    ->whereIn('entiteit_id', fn ($sub) => $sub->select('soa_regels.id')->from('soa_regels')
                        ->join('maatregelen', 'maatregelen.id', '=', 'soa_regels.maatregel_id')
                        ->where('maatregelen.annex_a_referentie', $nummer)))
                ->orWhere(fn ($s) => $s
                    ->where('entiteit_type', 'overheidsmaatregel_beoordeling')
                    ->whereIn('entiteit_id', fn ($sub) => $sub->select('overheidsmaatregel_beoordelingen.id')
                        ->from('overheidsmaatregel_beoordelingen')
                        ->join('soa_regels', 'soa_regels.id', '=', 'overheidsmaatregel_beoordelingen.soa_regel_id')
                        ->join('maatregelen', 'maatregelen.id', '=', 'soa_regels.maatregel_id')
                        ->where('maatregelen.annex_a_referentie', $nummer)))))
            ->orderByDesc('geupload_op')
            ->orderByDesc('id');

        $totaal = (clone $query)->count();
        $rijen = $query->forPage($pagina, $limiet)->get();

        app(Aanroepcontext::class)->resultaten($rijen->count());

        if (isset($invoer['id']) && $totaal === 0) {
            return Response::error("Geen bewijsstuk met id {$invoer['id']} dat u mag inzien.");
        }

        return Response::structured([
            'totaal' => $totaal,
            'pagina' => $pagina,
            'volgende_pagina' => $totaal > $pagina * $limiet ? $pagina + 1 : null,
            'bewijsstukken' => $rijen->map(fn (Bewijsstuk $b) => $this->rij($b))->all(),
            'url' => route('bewijsstukken.index', ['filterStatus' => '']),
        ]);
    }

    /** @return array<string, mixed> */
    private function rij(Bewijsstuk $bewijsstuk): array
    {
        return [
            'id' => $bewijsstuk->id,
            'naam' => $bewijsstuk->naam,
            'omschrijving' => $bewijsstuk->omschrijving,
            'bestandsnaam' => $bewijsstuk->bestandsnaam,
            'bestandstype' => $bewijsstuk->bestandstype,
            'bestandsgrootte' => $bewijsstuk->bestandsgrootte,
            'status' => self::enum($bewijsstuk->status),
            'geupload_op' => $bewijsstuk->geupload_op?->toIso8601String(),
            'leeftijd_dagen' => $bewijsstuk->geupload_op ? (int) $bewijsstuk->geupload_op->diffInDays(now()) : null,
            'geupload_door' => $bewijsstuk->uploader?->naam,
            'bewaren_tot' => $bewijsstuk->bewaren_tot?->toDateString(),
            'koppelingen' => $bewijsstuk->koppelingen->map(fn (BewijsKoppeling $k) => [
                'blok' => $k->blok_naam,
                'type' => self::enum($k->entiteit_type, array_map(fn (array $t) => $t['label'], Koppelbaar::TYPES)),
                'entiteit_id' => $k->entiteit_id,
                'omschrijving' => $k->omschrijving(),
            ])->values()->all(),
            'resource_uri' => 'isms://bewijs/'.$bewijsstuk->id,
            'url' => route('bewijsstukken.download', $bewijsstuk),
        ];
    }
}
