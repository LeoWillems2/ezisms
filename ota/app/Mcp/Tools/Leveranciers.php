<?php

namespace App\Mcp\Tools;

use App\Models\Contractclausule;
use App\Models\Leverancier;
use App\Models\Leveranciersbeoordeling;
use App\Support\Assistent\Aanroepcontext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Het leveranciersregister (implementatie/14b §5), zoals /leveranciers en het
 * leveranciersdossier het tonen.
 *
 * Het blok kent geen record-scoping (routes/web.php, blok 9): wie `lezen` heeft
 * ziet het hele register, dus de query is die van `LeveranciersOverzicht`. De
 * velden per leverancier zijn die van `LeverancierDetail`: clausules per vast
 * type (een ontbrekende rij is "afwezig"), diensten met systemen, de nieuwste
 * beoordeling en de gekoppelde risico's (het dossier toont die read-only).
 * Bewijsstukken staan hier niet in; daarvoor is de tool voor bewijs.
 */
#[IsReadOnly]
#[Description('Leveranciers en derdenrisico: per leverancier status, risiconiveau, eigen certificaat (geldig tot), diensten met systemen, de nieuwste beoordeling, contractclausules (aanwezig of ontbrekend) en open punten zoals een verstreken herbeoordeling of hoog risico zonder recht op audit. Gebruik dit voor vragen over leveranciersbeheer, verlopende certificaten of ontbrekende contractafspraken (Bijlage A 5.19–5.23).')]
class Leveranciers extends IsmsTool
{
    private const STANDAARD_LIMIET = 50;

    /** @var array<string, string> */
    private const STATUS_LABELS = [
        'kandidaat' => 'Kandidaat',
        'actief' => 'Actief',
        'beeindigd' => 'Beëindigd',
    ];

    /** @var array<string, string> */
    private const NIVEAU_LABELS = [
        'laag' => 'Laag',
        'midden' => 'Midden',
        'hoog' => 'Hoog',
    ];

    protected function vereist(): ?array
    {
        // Gelijk aan de route van leveranciers.index en leveranciers.detail.
        return ['leveranciers-derdenrisico', 'lezen'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(array_keys(self::STATUS_LABELS))
                ->description('Alleen leveranciers met deze status.'),
            'risiconiveau' => $schema->string()
                ->enum(array_keys(self::NIVEAU_LABELS))
                ->description('Alleen leveranciers met dit risiconiveau.'),
            'certificaat_verloopt_binnen_dagen' => $schema->integer()
                ->min(0)
                ->max(3650)
                ->description('Alleen leveranciers waarvan het eigen certificaat binnen zoveel dagen verloopt of al verlopen is.'),
            'leverancier_id' => $schema->integer()
                ->description('Alleen deze ene leverancier.'),
            'limiet' => $schema->integer()
                ->min(1)
                ->max(self::MAX_RIJEN)
                ->description('Hoeveel leveranciers hooguit. Standaard '.self::STANDAARD_LIMIET.'.'),
        ];
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $invoer = $request->validate([
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_keys(self::STATUS_LABELS))],
            'risiconiveau' => ['sometimes', 'string', 'in:'.implode(',', array_keys(self::NIVEAU_LABELS))],
            'certificaat_verloopt_binnen_dagen' => ['sometimes', 'integer', 'min:0', 'max:3650'],
            'leverancier_id' => ['sometimes', 'integer'],
            'limiet' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RIJEN],
        ]);

        $limiet = (int) ($invoer['limiet'] ?? self::STANDAARD_LIMIET);

        // Dezelfde set als het overzichtsscherm; de signalen tellen over het
        // hele register, niet over de filter (zoals daar).
        $alle = Leverancier::query()
            ->with([
                'nieuwsteBeoordeling.uitvoerder',
                'contractclausules',
                'diensten' => fn ($q) => $q->with('systemen')->orderBy('omschrijving'),
                'risicos' => fn ($q) => $q->orderBy('titel'),
                'teruggaveDoor',
            ])
            ->orderBy('naam')
            ->get();

        $lijst = $alle
            ->when(isset($invoer['status']), fn ($c) => $c->where('status', $invoer['status']))
            ->when(isset($invoer['risiconiveau']), fn ($c) => $c->where('risiconiveau', $invoer['risiconiveau']))
            ->when(isset($invoer['leverancier_id']), fn ($c) => $c->where('id', (int) $invoer['leverancier_id']))
            ->when(isset($invoer['certificaat_verloopt_binnen_dagen']), function ($c) use ($invoer) {
                $grens = now()->startOfDay()->addDays((int) $invoer['certificaat_verloopt_binnen_dagen']);

                return $c->filter(fn (Leverancier $l) => $l->eigen_certificering_geldig_tot !== null
                    && $l->eigen_certificering_geldig_tot->lessThanOrEqualTo($grens));
            })
            ->values();

        $getoond = $lijst->take($limiet);

        app(Aanroepcontext::class)->resultaten($getoond->count());

        return Response::structured([
            'totaal' => $lijst->count(),
            'getoond' => $getoond->count(),
            'signalen' => [
                'herbeoordeling_verstreken' => $alle->filter->herbeoordelingVerstreken()->count(),
                'hoog_risico_zonder_recht_op_audit' => $alle->filter->isHoogRisicoZonderAuditrecht()->count(),
            ],
            'leveranciers' => $getoond->map(fn (Leverancier $l) => $this->rij($l))->all(),
            'url' => route('leveranciers.index'),
        ]);
    }

    /** @return array<string, mixed> */
    private function rij(Leverancier $leverancier): array
    {
        $aanwezig = $leverancier->contractclausules->pluck('aanwezig', 'type');

        $clausules = [];
        $ontbrekend = [];
        foreach (Contractclausule::TYPES as $type => $label) {
            $isAanwezig = (bool) ($aanwezig[$type] ?? false);
            $clausules[] = ['code' => $type, 'label' => $label, 'aanwezig' => $isAanwezig];
            if (! $isAanwezig) {
                $ontbrekend[] = $label;
            }
        }

        $certificaat = $leverancier->eigen_certificering_geldig_tot;
        $beoordeling = $leverancier->nieuwsteBeoordeling;

        $openPunten = [];
        if ($leverancier->herbeoordelingVerstreken()) {
            $openPunten[] = 'Herbeoordeling verstreken (gepland op '.$leverancier->volgendeBeoordelingGepland()->toDateString().').';
        }
        if ($leverancier->isHoogRisicoZonderAuditrecht()) {
            $openPunten[] = 'Hoog risico zonder aantoonbaar recht op audit (geen clausule en geen geldig eigen certificaat).';
        }
        if ($certificaat !== null && $certificaat->isPast()) {
            $openPunten[] = 'Eigen certificaat verlopen op '.$certificaat->toDateString().'.';
        }
        if ($leverancier->status !== 'beeindigd' && $ontbrekend !== []) {
            $openPunten[] = 'Ontbrekende contractclausules: '.implode(', ', $ontbrekend).'.';
        }

        return [
            'id' => $leverancier->id,
            'naam' => $leverancier->naam,
            'status' => self::enum($leverancier->status, self::STATUS_LABELS),
            'risiconiveau' => self::enum($leverancier->risiconiveau, self::NIVEAU_LABELS),
            'eigen_certificering_geldig_tot' => $certificaat?->toDateString(),
            'certificaat_verlopen' => $certificaat !== null && $certificaat->isPast(),
            'recht_op_audit' => $leverancier->heeftRechtOpAudit(),
            'beeindigd' => $leverancier->status === 'beeindigd' ? [
                'op' => $leverancier->beeindigd_op?->toDateString(),
                'data_teruggave_bevestigd_op' => $leverancier->data_teruggave_bevestigd_op?->toIso8601String(),
                'door' => $leverancier->teruggaveDoor?->naam,
            ] : null,
            'diensten' => $leverancier->diensten->map(fn ($dienst) => [
                'omschrijving' => $dienst->omschrijving,
                'systemen' => $dienst->systemen->pluck('naam')->values()->all(),
            ])->all(),
            'nieuwste_beoordeling' => $beoordeling !== null ? $this->beoordeling($beoordeling) : null,
            'volgende_beoordeling_gepland' => $leverancier->volgendeBeoordelingGepland()?->toDateString(),
            'contractclausules' => $clausules,
            'gekoppelde_risicos' => $leverancier->risicos->map(fn ($risico) => [
                'titel' => $risico->titel,
                'status' => self::enum($risico->status),
            ])->all(),
            'open_punten' => $openPunten,
            'url' => route('leveranciers.detail', $leverancier),
        ];
    }

    /** @return array<string, mixed> */
    private function beoordeling(Leveranciersbeoordeling $beoordeling): array
    {
        return [
            'uitgevoerd_op' => $beoordeling->uitgevoerd_op?->toDateString(),
            'uitgevoerd_door' => $beoordeling->uitvoerder?->naam,
            'bevindingen' => $beoordeling->bevindingen,
            'volgende_beoordeling_gepland' => $beoordeling->volgende_beoordeling_gepland?->toDateString(),
        ];
    }
}
