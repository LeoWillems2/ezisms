<?php

namespace App\Mcp\Tools;

use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Doelgroep;
use App\Models\Leesbevestiging;
use App\Models\Toetsopdracht;
use App\Models\Trainingsmodule;
use App\Support\Assistent\Aanroepcontext;
use App\Support\Leesbevestigingslijst;
use App\Support\Leesbevestigingsstand;
use App\Support\Recordscope;
use App\Support\Trainingsstand;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Bewustzijn, training en leesbevestigingen (implementatie/14b §5).
 *
 * Twee blokken, en per blok twee lagen, precies zoals de schermen:
 *
 * - **Training** (`bewustzijn-training`). Het programma (/trainingen,
 *   /doelgroepen) en de toetsresultaten (/toetsen/resultaten) staan op `lezen`,
 *   maar de componenten laten alleen toe wie `Recordscope::magAllesZien` heeft
 *   (CISO, Auditor). De eigen trainingen (/mijn-trainingen) vragen `uitvoeren`.
 *   Geen scherm toont voltooiing per persoon per module; het programma toont
 *   per module graad en aantal verlopen, de toetsresultaten tonen per persoon.
 * - **Leesbevestigingen** (`beleid-maatregelbeheer`, `uitvoeren`, de route van
 *   /beleid). De stand over iedereen (dashboardpaneel) en wie per document wel
 *   of niet bevestigde (beleidsdetail) alleen bij `magAllesZien`; anders alleen
 *   de eigen openstaande bevestigingen.
 *
 * Wat de gebruiker niet mag zien, staat onder `weggelaten`. De deelnemerslink
 * van een toets (met token) gaat niet mee: die opent de toets namens de
 * gebruiker en hoort niet bij een assistent.
 */
#[IsReadOnly]
#[Description('Bewustzijn en training: trainingsmodules met doelgroepen, voltooiingsgraad en verlopen voltooiingen, toetsresultaten per deelnemer, de eigen trainingen en toetsen van de gebruiker, en leesbevestigingen van beleid (welke documenten achterlopen, wie per document wel of niet bevestigde, wat de gebruiker zelf nog moet bevestigen). Gebruik dit voor vragen over awareness (Bijlage A 6.3), trainingsachterstand of leesbevestigingen. Geef beleidsdocument_id voor de bevestigingen per persoon van één document.')]
class TrainingEnBewustzijn extends IsmsTool
{
    private const STANDAARD_LIMIET = 50;

    private const TRAINING = 'bewustzijn-training';

    private const BELEID = 'beleid-maatregelbeheer';

    /** @var array<string, string> */
    private const MODULESTATUS = [
        'voltooid' => 'Voltooid',
        'verlopen' => 'Verlopen',
        'te_doen' => 'Te doen',
    ];

    /** @var array<string, string> */
    private const TOETSSTATUS = [
        'uitgezet' => 'Uitgezet',
        'gezakt' => 'Gezakt',
        'geslaagd' => 'Geslaagd',
    ];

    /** @var array<string, string> */
    private const LEESREDEN = [
        Leesbevestigingsstand::REDEN_ACHTERSTAND => 'Nog niet iedereen in de doelgroep heeft bevestigd',
        Leesbevestigingsstand::REDEN_GEEN_DOELGROEP => 'Bevestigingsplicht zonder doelgroep (geen afdeling gekoppeld)',
    ];

    protected function vereist(): ?array
    {
        return null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'toets_status' => $schema->string()
                ->enum(['openstaand', ...array_keys(self::TOETSSTATUS), 'alle'])
                ->description('Filter op de toetsresultaten. Standaard "openstaand" (nog niet geslaagd), zoals het resultatenscherm.'),
            'beleidsdocument_id' => $schema->integer()
                ->description('Toon voor dit beleidsdocument wie de geldende versie wel en niet heeft bevestigd.'),
            'limiet' => $schema->integer()
                ->min(1)->max(self::MAX_RIJEN)
                ->description('Hoeveel toetsresultaten en documenten hooguit. Standaard '.self::STANDAARD_LIMIET.'.'),
        ];
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $invoer = $request->validate([
            'toets_status' => ['sometimes', 'string', 'in:'.implode(',', ['openstaand', ...array_keys(self::TOETSSTATUS), 'alle'])],
            'beleidsdocument_id' => ['sometimes', 'integer'],
            'limiet' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RIJEN],
        ]);

        $limiet = (int) ($invoer['limiet'] ?? self::STANDAARD_LIMIET);
        $gebruiker = auth()->user();

        $uitvoer = ['peildatum' => now()->toIso8601String()];
        $weggelaten = [];
        $aantal = 0;

        // ── Training ────────────────────────────────────────────────────────
        if ($this->mag(self::TRAINING, 'lezen')) {
            if (Recordscope::magAllesZien(self::TRAINING)) {
                $uitvoer['programma'] = $this->programma();
                $uitvoer['toetsresultaten'] = $this->toetsresultaten($invoer['toets_status'] ?? 'openstaand', $limiet);
                $aantal += count($uitvoer['programma']['modules']) + count($uitvoer['toetsresultaten']['lijst']);
            } else {
                $weggelaten[] = 'trainingsprogramma, doelgroepen en toetsresultaten van anderen (alleen voor wie het hele blok Bewustzijn & Training mag inzien)';
            }

            if ($this->mag(self::TRAINING, 'uitvoeren')) {
                $uitvoer['mijn_trainingen'] = $this->mijnTrainingen($gebruiker);
                $aantal += count($uitvoer['mijn_trainingen']['modules']);
            } else {
                $weggelaten[] = 'eigen trainingen (Mijn trainingen vraagt het recht om zelf trainingen uit te voeren)';
            }
        } else {
            $weggelaten[] = 'training en toetsen (geen leesrecht op Bewustzijn & Training)';
        }

        // ── Leesbevestigingen ───────────────────────────────────────────────
        if ($this->mag(self::BELEID, 'uitvoeren')) {
            $leesbevestiging = [];

            if (Recordscope::magAllesZien(self::BELEID)) {
                $leesbevestiging['stand'] = $this->leesstand($limiet);
                $aantal += count($leesbevestiging['stand']['aandacht']);

                if (isset($invoer['beleidsdocument_id'])) {
                    $leesbevestiging['per_persoon'] = $this->perPersoon((int) $invoer['beleidsdocument_id']);
                }
            } else {
                $weggelaten[] = 'leesbevestigingen van anderen (alleen voor wie het hele blok Beleid & Maatregelbeheer mag inzien)';
            }

            $leesbevestiging['zelf_nog_te_bevestigen'] = $this->eigenOpenstaand($gebruiker);
            $uitvoer['leesbevestigingen'] = $leesbevestiging;
        } else {
            $weggelaten[] = 'leesbevestigingen (geen toegang tot Beleid & Maatregelbeheer)';
        }

        $uitvoer['weggelaten'] = $weggelaten;

        app(Aanroepcontext::class)->resultaten($aantal);

        return Response::structured($uitvoer);
    }

    private function mag(string $blok, string $niveau): bool
    {
        return Gate::allows('heeft-niveau', [$blok, $niveau]);
    }

    /** Wat /trainingen en /doelgroepen tonen. @return array<string, mixed> */
    private function programma(): array
    {
        $modules = Trainingsmodule::query()
            ->with(['doelgroepen', 'beleidsdocumenten'])
            ->orderBy('titel')
            ->get();

        // De gekoppelde beleidsdocumenten staan alleen in het bewerkformulier,
        // en dat is er alleen voor wie muteert.
        $metBeleid = $this->mag(self::TRAINING, 'muteren');

        return [
            'modules' => $modules->map(function (Trainingsmodule $module) use ($metBeleid) {
                $cijfers = Trainingsstand::moduleCijfers($module);

                return [
                    'id' => $module->id,
                    'titel' => $module->titel,
                    'actief' => (bool) $module->actief,
                    'geldigheidsduur_maanden' => $module->geldigheidsduur_maanden,
                    'toets' => $module->toets_bestand,
                    'doelgroepen' => $module->doelgroepen->pluck('naam')->values()->all(),
                    'beleidsdocumenten' => $metBeleid ? $module->beleidsdocumenten->pluck('titel')->values()->all() : null,
                    'doelgroep_grootte' => $cijfers['doelgroepGrootte'],
                    'voltooiingsgraad' => $cijfers['graad'],
                    'verlopen' => $cijfers['verlopen'],
                    'url' => route('trainingen.index'),
                ];
            })->all(),
            'doelgroepen' => Doelgroep::withCount(['gebruikers', 'modules'])->orderBy('naam')->get()
                ->map(fn (Doelgroep $doelgroep) => [
                    'id' => $doelgroep->id,
                    'naam' => $doelgroep->naam,
                    'omschrijving' => $doelgroep->omschrijving,
                    'leden' => (int) $doelgroep->gebruikers_count,
                    'modules' => (int) $doelgroep->modules_count,
                    'url' => route('doelgroepen.index'),
                ])->all(),
            'signalen' => [
                'actieve_modules_zonder_doelgroep' => Trainingsstand::modulesZonderDoelgroep(),
                'toetsmodules_nooit_uitgezet' => Trainingsstand::toetsModulesNietUitgezet(),
            ],
        ];
    }

    /** Wat /toetsen/resultaten toont. @return array<string, mixed> */
    private function toetsresultaten(string $status, int $limiet): array
    {
        $opdrachten = Trainingsstand::toetsresultaten('', $status === 'alle' ? '' : $status);

        return [
            'filter' => $status,
            'totaal' => $opdrachten->count(),
            'lijst' => $opdrachten->take($limiet)->map(fn (Toetsopdracht $opdracht) => [
                'id' => $opdracht->id,
                'toets' => $opdracht->toets_titel,
                'deelnemer' => $opdracht->taak?->eigenaar?->naam,
                'status' => self::enum($opdracht->status, self::TOETSSTATUS),
                'laatste_score' => $opdracht->laatste_score,
                'laatste_totaal' => $opdracht->laatste_totaal,
                'pogingen' => $opdracht->pogingen,
                'deadline' => $opdracht->taak?->deadline?->toDateString(),
                'geslaagd_op' => $opdracht->geslaagd_op?->toIso8601String(),
                'url' => route('toetsen.resultaten'),
            ])->values()->all(),
            'url' => route('toetsen.resultaten'),
        ];
    }

    /** Wat /mijn-trainingen de gebruiker zelf toont. @return array<string, mixed> */
    private function mijnTrainingen($gebruiker): array
    {
        $toets = fn (?Toetsopdracht $opdracht) => $opdracht === null ? null : [
            'titel' => $opdracht->toets_titel,
            'status' => self::enum($opdracht->status, self::TOETSSTATUS),
            'deadline' => $opdracht->taak?->deadline?->toDateString(),
        ];

        return [
            'modules' => Trainingsstand::eigenModules($gebruiker)->map(fn (array $rij) => [
                'id' => $rij['module']->id,
                'titel' => $rij['module']->titel,
                'geldigheidsduur_maanden' => $rij['module']->geldigheidsduur_maanden,
                'status' => self::enum($rij['status'], self::MODULESTATUS),
                'met_toets' => $rij['module']->heeftToets(),
                'toets' => $toets($rij['toets']),
            ])->values()->all(),
            'losse_toetsen' => Trainingsstand::losseToetsen($gebruiker)
                ->map(fn (Toetsopdracht $opdracht) => $toets($opdracht))->values()->all(),
            'url' => route('mijn-trainingen.index'),
        ];
    }

    /** Het dashboardpaneel Leesbevestiging. @return array<string, mixed> */
    private function leesstand(int $limiet): array
    {
        $stand = Leesbevestigingsstand::huidige();

        return [
            'volledig_bevestigd' => $stand->volledig,
            'actieve_gebruikers_zonder_afdeling' => $stand->zonderAfdeling,
            'over_de_termijn' => $stand->overDeTermijn(),
            'zonder_doelgroep' => $stand->zonderDoelgroep(),
            'totaal_aandacht' => count($stand->aandacht),
            'aandacht' => array_map(fn (array $regel) => [
                'beleidsdocument_id' => $regel['document']->id,
                'titel' => $regel['document']->titel,
                'versienummer' => $regel['versienummer'],
                'reden' => self::enum($regel['reden'], self::LEESREDEN),
                'bevestigd' => $regel['bevestigd'],
                'doelgroep' => $regel['doelgroep'],
                'openstaand' => $regel['openstaand'],
                'graad' => $regel['graad'],
                'deadline' => $regel['deadline']->toDateString(),
                'termijn_verstreken' => $regel['verstreken'],
                'url' => route('beleid.detail', $regel['document']),
            ], array_slice($stand->aandacht, 0, $limiet)),
            'url' => route('dashboard'),
        ];
    }

    /** Wat het beleidsdetail onder de geldende versie toont. @return array<string, mixed> */
    private function perPersoon(int $documentId): array
    {
        $document = Beleidsdocument::query()->zichtbaar()->with('actieveVersie')->find($documentId);

        if ($document === null) {
            return ['beleidsdocument_id' => $documentId, 'gevonden' => false];
        }

        $actieve = $document->actieveVersie;
        $lijst = $actieve !== null ? Leesbevestigingslijst::voor($document, $actieve) : Leesbevestigingslijst::leeg();

        return [
            'beleidsdocument_id' => $document->id,
            'gevonden' => true,
            'titel' => $document->titel,
            'leesbevestiging_vereist' => (bool) $document->leesbevestiging_vereist,
            'versienummer' => $actieve?->versienummer,
            'bevestigd' => $lijst->bevestigingen->map(fn (Leesbevestiging $b) => [
                'naam' => $b->gebruiker?->naam,
                'bevestigd_op' => $b->bevestigd_op?->toIso8601String(),
                'zonder_het_bestand_op_te_halen' => $lijst->zonderRaadpleging[$b->gebruiker_id] ?? false,
            ])->values()->all(),
            'niet_bevestigd' => $lijst->nietBevestigd->pluck('naam')->values()->all(),
            'url' => route('beleid.detail', $document),
        ];
    }

    /** De waarschuwing op /beleid, als lijst. @return list<array<string, mixed>> */
    private function eigenOpenstaand($gebruiker): array
    {
        return Leesbevestigingslijst::openstaandVoor($gebruiker)
            ->with('document')
            ->get()
            ->sortBy(fn (Beleidsversie $versie) => $versie->document?->titel)
            ->map(fn (Beleidsversie $versie) => [
                'titel' => $versie->document?->titel,
                'versienummer' => $versie->versienummer,
                'deadline' => $versie->leesdeadline()->toDateString(),
                'termijn_verstreken' => $versie->leestermijnVerstreken(),
                'url' => route('beleid.detail', $versie->beleidsdocument_id),
            ])
            ->values()
            ->all();
    }
}
