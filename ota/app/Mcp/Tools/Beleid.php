<?php

namespace App\Mcp\Tools;

use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Support\Assistent\Aanroepcontext;
use App\Support\Recordscope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Beleidsdocumenten met hun geldende versie (implementatie/14b §5).
 *
 * Dezelfde scope als `BeleidsdocumentenOverzicht`: `zichtbaar()`, dus wie niet
 * alles mag zien, krijgt alleen actieve documenten. De rest volgt de lijst en
 * het detailscherm:
 *
 * - Een versie die op goedkeuring wacht, staat er alleen bij voor wie alles
 *   ziet; een Medewerker hoort niet te lezen dat er nieuw beleid klaarligt (05 §9).
 * - De bevestigingsgraad idem. Wie niet alles ziet, krijgt wat de kolom
 *   "Bevestigd" hem toont: of hij zelf heeft bevestigd en of hij in de
 *   doelgroep valt.
 */
#[IsReadOnly]
#[Description('Beleidsdocumenten en procedures met de geldende (actieve) versie: versienummer, publicatiedatum, wie goedkeurde, geplande herziening en of die verstreken is, en de leesbevestiging. Elke actieve versie heeft een resource_uri voor de tekst. Gebruik dit voor vragen als "welk beleid moet herzien worden", "wie keurde het wachtwoordbeleid goed" of om de tekst van een beleidsdocument op te halen.')]
class Beleid extends IsmsTool
{
    private const STANDAARD_LIMIET = 50;

    private const STATUSSEN = ['concept', 'ter_goedkeuring', 'actief', 'ingetrokken'];

    private const TYPES = ['beleid', 'procedure'];

    protected function vereist(): ?array
    {
        // routes/web.php: /beleid en /beleid/{beleidsdocument} staan achter heeft-niveau,'beleid-maatregelbeheer','uitvoeren'.
        return ['beleid-maatregelbeheer', 'uitvoeren'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->description('Eén beleidsdocument op id.'),
            'status' => $schema->string()->enum(self::STATUSSEN)
                ->description('Alleen documenten met deze status. "ter_goedkeuring" omvat ook actieve documenten met een aangeboden nieuwe versie.'),
            'type' => $schema->string()->enum(self::TYPES)->description('Alleen beleid of alleen procedures.'),
            'herziening_verstreken' => $schema->boolean()
                ->description('Alleen documenten waarvan de geplande herziening van de actieve versie verstreken is.'),
            'limiet' => $schema->integer()->min(1)->max(self::MAX_RIJEN)
                ->description('Hoeveel documenten per pagina. Standaard '.self::STANDAARD_LIMIET.'.'),
            'pagina' => $schema->integer()->min(1)->description('Paginanummer, vanaf 1.'),
        ];
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $invoer = $request->validate([
            'id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', 'in:'.implode(',', self::STATUSSEN)],
            'type' => ['sometimes', 'nullable', 'in:'.implode(',', self::TYPES)],
            'herziening_verstreken' => ['sometimes', 'boolean'],
            'limiet' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RIJEN],
            'pagina' => ['sometimes', 'integer', 'min:1'],
        ]);

        $limiet = (int) ($invoer['limiet'] ?? self::STANDAARD_LIMIET);
        $pagina = (int) ($invoer['pagina'] ?? 1);
        $allesZien = Recordscope::magAllesZien('beleid-maatregelbeheer');
        $status = (string) ($invoer['status'] ?? '');

        $query = Beleidsdocument::query()
            ->zichtbaar()
            ->with(['eigenaar', 'actieveVersie.goedkeurder', ...($allesZien ? ['versieTerGoedkeuring'] : [])])
            ->when(isset($invoer['id']), fn ($q) => $q->whereKey($invoer['id']))
            // Zoals het scherm: het statusfilter verbreedt naar wachtende versies,
            // maar alleen voor wie die mag zien (BeleidsdocumentenOverzicht::zoektOpAanbieding).
            ->when($status !== '', fn ($q) => $status === 'ter_goedkeuring' && $allesZien
                ? $q->where(fn ($w) => $w->where('status', 'ter_goedkeuring')
                    ->orWhereHas('versies', fn ($v) => $v->where('status', 'ter_goedkeuring')))
                : $q->where('status', $status))
            ->when(isset($invoer['type']), fn ($q) => $q->where('type', $invoer['type']))
            ->when($invoer['herziening_verstreken'] ?? false, fn ($q) => $q->whereHas('actieveVersie', fn ($v) => $v
                ->whereNotNull('volgende_herziening_gepland')
                ->where('volgende_herziening_gepland', '<', now()->toDateString())))
            ->orderBy('titel')
            ->orderBy('id');

        $totaal = (clone $query)->count();
        $rijen = $query->forPage($pagina, $limiet)->get();

        app(Aanroepcontext::class)->resultaten($rijen->count());

        if (isset($invoer['id']) && $totaal === 0) {
            return Response::error("Geen beleidsdocument met id {$invoer['id']} dat u mag inzien.");
        }

        return Response::structured([
            'totaal' => $totaal,
            'pagina' => $pagina,
            'volgende_pagina' => $totaal > $pagina * $limiet ? $pagina + 1 : null,
            'documenten' => $rijen->map(fn (Beleidsdocument $d) => $this->rij($d, $allesZien))->all(),
            'url' => route('beleid.index'),
        ]);
    }

    /** @return array<string, mixed> */
    private function rij(Beleidsdocument $document, bool $allesZien): array
    {
        $versie = $document->actieveVersie;
        $gebruiker = auth()->user();

        $rij = [
            'id' => $document->id,
            'titel' => $document->titel,
            'type' => self::enum($document->type),
            'status' => self::enum($document->status),
            'omschrijving' => $document->omschrijving,
            'eigenaar' => $document->eigenaar === null ? null : [
                'naam' => $document->eigenaar->naam,
                'actief' => $document->eigenaar->isActief(),
            ],
            'ingetrokken_op' => $document->ingetrokken_op?->toDateString(),
            'actieve_versie' => $versie === null ? null : $this->versie($document, $versie),
            'leesbevestiging' => [
                'vereist' => $document->leesbevestiging_vereist,
                // Zoals de kolom "Bevestigd": de graad alleen voor wie alles ziet.
                ...($allesZien
                    ? ['bevestigingsgraad' => $document->bevestigingsgraad()]
                    : [
                        'in_doelgroep' => $document->isInDoelgroep($gebruiker),
                        'zelf_bevestigd' => $versie?->isBevestigdDoor($gebruiker?->id) ?? false,
                    ]),
            ],
            'url' => route('beleid.detail', $document),
        ];

        if ($allesZien && ! $document->isIngetrokken() && $document->versieTerGoedkeuring) {
            $wachtend = $document->versieTerGoedkeuring;
            $rij['versie_ter_goedkeuring'] = [
                'versienummer' => $wachtend->versienummer,
                'aangeboden_op' => $wachtend->aangeboden_op?->toIso8601String(),
                'goedkeurtermijn_verstreken' => $wachtend->goedkeurtermijnVerstreken(),
            ];
        }

        return $rij;
    }

    /** @return array<string, mixed> */
    private function versie(Beleidsdocument $document, Beleidsversie $versie): array
    {
        return [
            'versienummer' => $versie->versienummer,
            'status' => self::enum($versie->status),
            'gepubliceerd_op' => $versie->gepubliceerd_op?->toDateString(),
            'goedgekeurd_door' => $versie->goedkeurder?->naam,
            'goedgekeurd_op' => $versie->goedgekeurd_op?->toIso8601String(),
            'volgende_herziening_gepland' => $versie->volgende_herziening_gepland?->toDateString(),
            'herziening_verstreken' => $versie->herzieningVerstreken(),
            'wijzigingsreden' => $versie->wijzigingsreden,
            'bestand_url' => $versie->bewijsstuk_id !== null
                ? route('bewijsstukken.download', $versie->bewijsstuk_id)
                : null,
            'resource_uri' => 'isms://beleid/'.$document->id.'/versie/'.$versie->versienummer,
        ];
    }
}
