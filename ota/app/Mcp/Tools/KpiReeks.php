<?php

namespace App\Mcp\Tools;

use App\Models\KpiDefinitie;
use App\Models\Meting;
use App\Support\Assistent\Aanroepcontext;
use App\Support\Kpitrend;
use App\Support\Meetbronnen;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * KPI-definities en hun meetreeks (implementatie/14b §5), zoals de meetaanpak
 * (`/meetaanpak`) ze toont: de hele catalogus, ook inactieve KPI's, met per
 * meetpunt teller, noemer, de streef- en signaalwaarde van dát meetpunt, de
 * definitieversie, herkomst en toelichting.
 *
 * Rekent niets zelf uit: uitkomst, status, delta en richting komen uit
 * {@see Kpitrend}, dezelfde klasse als het dashboard en de meetaanpak. De trend
 * loopt over de volledige reeks (zoals de dashboardtegel); de periode beperkt
 * alleen welke meetpunten worden teruggegeven.
 */
#[IsReadOnly]
#[Description('KPI-definities (sleutel, naam, PDCA-fase, eenheid, richting, berekeningswijze, streef- en signaalwaarde, definitieversie, meetbron) met hun meetpunten over een optionele periode: teller, noemer, uitkomst, status en definitieversie per meetpunt, plus de trend (status, delta over 12 maanden, richting). Gebruik dit om KPI-reeksen te analyseren of om een KPI samen te stellen uit meerdere bronnen: teller en noemer zijn de ruwe getallen, de uitkomst is daaruit afgeleid. Let op definitie_versie: verschilt die tussen meetpunten, dan is de meetmethode veranderd en zijn die punten niet zonder meer vergelijkbaar (versiebreuk, zie versiebreuk_in_delta). Streef- en signaalwaarde staan per meetpunt omdat ze in de tijd kunnen wijzigen.')]
class KpiReeks extends IsmsTool
{
    private const FASE_LABELS = ['plan' => 'Plan', 'do' => 'Do', 'check' => 'Check', 'act' => 'Act'];

    private const EENHEID_LABELS = ['ratio' => 'Percentage', 'dagen' => 'Gemiddelde in dagen', 'aantal' => 'Aantal'];

    private const RICHTING_LABELS = ['omhoog' => 'Hoger is beter', 'omlaag' => 'Lager is beter'];

    private const BEWEGING_LABELS = ['op' => 'De goede kant op', 'neer' => 'De verkeerde kant op', 'vlak' => 'Geen beweging of niets te vergelijken'];

    protected function vereist(): ?array
    {
        // Overgenomen van de route van /meetaanpak (routes/web.php).
        return ['management-review-verbetercyclus', 'lezen'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sleutels' => $schema->array()
                ->items($schema->string())
                ->description('Alleen deze KPI\'s, op sleutel. Weggelaten: alle KPI\'s.'),
            'fase' => $schema->string()
                ->enum(array_keys(self::FASE_LABELS))
                ->description('Alleen KPI\'s uit deze PDCA-fase.'),
            'alleen_actief' => $schema->boolean()
                ->description('Alleen KPI\'s die nog gemeten worden. Standaard false: de meetaanpak toont ook inactieve KPI\'s met hun historie.'),
            'van' => $schema->string()->format('date')
                ->description('Meetpunten vanaf deze datum (JJJJ-MM-DD, inclusief).'),
            'tot' => $schema->string()->format('date')
                ->description('Meetpunten tot en met deze datum (JJJJ-MM-DD).'),
            'limiet' => $schema->integer()->min(1)->max(self::MAX_RIJEN)
                ->description('Hoeveel meetpunten per KPI hooguit, de meest recente eerst. Standaard '.self::MAX_RIJEN.'.'),
        ];
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $invoer = $request->validate([
            'sleutels' => ['sometimes', 'array'],
            'sleutels.*' => ['string', 'max:255'],
            'fase' => ['sometimes', 'string', 'in:'.implode(',', array_keys(self::FASE_LABELS))],
            'alleen_actief' => ['sometimes', 'boolean'],
            'van' => ['sometimes', 'date'],
            'tot' => ['sometimes', 'date'],
            'limiet' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RIJEN],
        ]);

        $van = isset($invoer['van']) ? Carbon::parse($invoer['van'])->startOfDay() : null;
        $tot = isset($invoer['tot']) ? Carbon::parse($invoer['tot'])->endOfDay() : null;
        $limiet = (int) ($invoer['limiet'] ?? self::MAX_RIJEN);

        // Dezelfde query als MeetaanpakOverzicht::render(): geen record-scoping,
        // wie de meetaanpak mag lezen ziet de hele catalogus.
        $definities = KpiDefinitie::query()
            ->with(['metingen' => fn ($q) => $q->orderBy('gemeten_op'), 'metingen.ingevoerdDoor:id,naam'])
            ->when(($invoer['sleutels'] ?? []) !== [], fn ($q) => $q->whereIn('sleutel', $invoer['sleutels']))
            ->when(isset($invoer['fase']), fn ($q) => $q->where('fase', $invoer['fase']))
            ->when($invoer['alleen_actief'] ?? false, fn ($q) => $q->where('actief', true))
            ->orderBy('naam')
            ->get();

        $meetbronnen = Meetbronnen::keuzelijst();
        $aantalMetingen = 0;

        $kpis = $definities->map(function (KpiDefinitie $definitie) use ($van, $tot, $limiet, $meetbronnen, &$aantalMetingen): array {
            $trend = Kpitrend::van($definitie, $definitie->metingen);

            $inPeriode = $trend->metingen
                ->filter(fn (Meting $m) => ($van === null || $m->gemeten_op->greaterThanOrEqualTo($van))
                    && ($tot === null || $m->gemeten_op->lessThanOrEqualTo($tot)))
                ->values();
            $getoond = $inPeriode->slice(-$limiet)->values();
            $aantalMetingen += $getoond->count();

            return [
                'sleutel' => $definitie->sleutel,
                'naam' => $definitie->naam,
                'fase' => self::enum($definitie->fase, self::FASE_LABELS),
                'eenheid' => self::enum($definitie->eenheid, self::EENHEID_LABELS),
                'richting' => self::enum($definitie->richting, self::RICHTING_LABELS),
                'berekeningswijze' => $definitie->berekeningswijze,
                'meetbron' => $definitie->meetbron === null
                    ? null
                    : self::enum($definitie->meetbron, $meetbronnen),
                'handmatig' => $definitie->isHandmatig(),
                'actief' => $definitie->actief,
                'streefwaarde' => $definitie->streefwaarde,
                'signaalwaarde' => $definitie->signaalwaarde,
                // Een niet-vastgestelde streefwaarde is een meegeleverd voorstel:
                // die kleurt geen meetpunten (12e §9).
                'streefwaarde_vastgesteld' => $definitie->streefwaardeIsVastgesteld(),
                'streefwaarde_vastgesteld_op' => $definitie->streefwaarde_vastgesteld_op?->toDateString(),
                'definitie_versie' => $definitie->definitie_versie,
                'trend' => $this->trend($trend),
                'metingen_totaal' => $definitie->metingen->count(),
                'metingen_in_periode' => $inPeriode->count(),
                'metingen' => $getoond->map(fn (Meting $m) => $this->meting($trend, $m))->all(),
                'url' => route('meetaanpak.index'),
            ];
        })->values()->all();

        app(Aanroepcontext::class)->resultaten(count($kpis) + $aantalMetingen);

        return Response::structured([
            'totaal' => count($kpis),
            'periode' => ['van' => $van?->toDateString(), 'tot' => $tot?->toDateString()],
            'kpis' => $kpis,
        ]);
    }

    /** @return array<string, mixed> */
    private function trend(Kpitrend $trend): array
    {
        $laatste = $trend->laatste();
        $basis = $trend->basis();
        $status = $trend->status();

        return [
            'status' => self::enum($status, [$status => Kpitrend::statusLabel($status)]),
            'laatste_uitkomst' => $laatste !== null ? $trend->uitkomst($laatste) : null,
            'laatst_gemeten_op' => $laatste?->gemeten_op?->toDateString(),
            'delta' => $trend->delta(),
            'delta_eenheid' => match (true) {
                $trend->inAantal() => 'aantal',
                $trend->inDagen() => 'dagen',
                default => 'procentpunten',
            },
            'delta_over_maanden' => Kpitrend::DELTA_MAANDEN,
            'basis_gemeten_op' => $basis?->gemeten_op?->toDateString(),
            'beweging' => self::enum($trend->richting(), self::BEWEGING_LABELS),
            // Liggen basis en laatste meetpunt in verschillende definitieversies,
            // dan vergelijkt de delta twee meetmethoden (12f).
            'versiebreuk_in_delta' => $laatste !== null && $basis !== null
                && $laatste->definitie_versie !== $basis->definitie_versie,
        ];
    }

    /** @return array<string, mixed> */
    private function meting(Kpitrend $trend, Meting $meting): array
    {
        $status = $trend->status($meting);

        return [
            'gemeten_op' => $meting->gemeten_op->toDateString(),
            'periode_van' => $meting->periode_van?->toIso8601String(),
            'periode_tot' => $meting->periode_tot?->toIso8601String(),
            'teller' => $meting->teller,
            'noemer' => $meting->noemer,
            'uitkomst' => $trend->uitkomst($meting),
            'streefwaarde' => $meting->streefwaarde,
            'signaalwaarde' => $meting->signaalwaarde,
            'status' => self::enum($status, [$status => Kpitrend::statusLabel($status)]),
            'definitie_versie' => $meting->definitie_versie,
            'herkomst' => $meting->herkomst(),
            'toelichting' => $meting->toelichting,
        ];
    }
}
