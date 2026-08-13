<?php

namespace App\Support;

use App\Models\Risico;
use App\Models\Systeemhartslag;
use App\Models\Taak;
use Illuminate\Support\Carbon;

/**
 * Het signalenpaneel van het dashboard (implementatie/12c §3.2).
 *
 * Dit paneel volgt het uitgangspunt uit blok 12 §4: **variantie boven groene
 * pijlen**. Een dashboard dat alleen maar vooruitgang toont, nodigt uit tot
 * precies het gedrag dat het zou moeten signaleren. Daarom staat hier niet de
 * score maar de afwijking, en telt een herstelde dip mee als *positief* signaal
 * — dat is bewijs dat de Check-fase iets meet.
 *
 * De signalen worden opgebouwd uit wat de kijker mag zien. Bewust met vlaggen
 * als parameter en niet met een eigen autorisatiecheck: het paneel zelf hangt al
 * achter een check, en een tweede check op een andere plek loopt daarmee uit
 * elkaar.
 */
final class Dashboardsignalen
{
    /** @var list<array{vlag: string, tekst: string, uitleg: string, getal: string}> */
    private array $signalen = [];

    /**
     * @param  list<Kpitrend>  $trends
     */
    public static function stel(
        array $trends,
        bool $magRisicoLezen,
        bool $magSoaLezen,
    ): self {
        $bouwer = new self;

        if ($magRisicoLezen) {
            $bouwer->risicosignalen();
        }

        if ($magSoaLezen) {
            $bouwer->maatregelsignalen();
        }

        $bouwer->kpisignalen($trends);
        $bouwer->stilgevallenHandmatigeKpis($trends);
        // Zonder rechtenvlag, anders dan de twee hierboven: dit gaat niet over
        // een register maar over de vraag of het ISMS zichzelf nog meet, en dat
        // hoort iedereen te zien die het dashboard mag openen
        // (implementatie/00m §11).
        $bouwer->onderbrokenBewaking();
        $bouwer->nietTeBerekenen();

        return $bouwer;
    }

    /** @return list<array{vlag: string, tekst: string, uitleg: string, getal: string}> */
    public function alle(): array
    {
        return $this->signalen;
    }

    private function voegToe(string $vlag, string $tekst, string $uitleg, string $getal): void
    {
        $this->signalen[] = compact('vlag', 'tekst', 'uitleg', 'getal');
    }

    private function risicosignalen(): void
    {
        $verdeling = Risicoverdeling::huidige();
        $boven = $verdeling->bovenDrempel();

        if ($boven > 0) {
            $this->voegToe(
                'kritiek',
                $boven === 1
                    ? '1 risico boven de acceptatiedrempel'
                    : "{$boven} risico's boven de acceptatiedrempel",
                'De drempel staat op '.Risico::drempelwaarde()
                    .'. Boven die grens hoort de directie het restrisico te accepteren.',
                'score > '.Risico::drempelwaarde(),
            );
        }

        $verstreken = Risico::query()
            ->whereNotNull('volgende_beoordeling_gepland')
            ->whereDate('volgende_beoordeling_gepland', '<', now())
            ->count();

        if ($verstreken > 0) {
            $this->voegToe(
                'let-op',
                $verstreken === 1
                    ? '1 risico is te lang niet herbeoordeeld'
                    : "{$verstreken} risico's zijn te lang niet herbeoordeeld",
                'De geplande herbeoordelingsdatum is verstreken.',
                (string) $verstreken,
            );
        }

        if ($verdeling->nietBeoordeeld > 0) {
            $this->voegToe(
                'let-op',
                "{$verdeling->nietBeoordeeld} risico's zijn nog niet beoordeeld",
                'Zonder kans en impact valt een risico buiten de matrix en buiten de KPI.',
                (string) $verdeling->nietBeoordeeld,
            );
        }
    }

    private function maatregelsignalen(): void
    {
        $verdeling = Maatregelverdeling::huidige();
        $open = $verdeling->nogNietGeimplementeerd();

        if ($open > 0) {
            $this->voegToe(
                'let-op',
                $open === 1
                    ? '1 toepasselijke maatregel is nog niet geïmplementeerd'
                    : "{$open} toepasselijke maatregelen zijn nog niet geïmplementeerd",
                'Van de '.$verdeling->toepasselijk().' toepasselijke regels in de SoA.',
                $open.' / '.$verdeling->toepasselijk(),
            );
        }
    }

    /** @param list<Kpitrend> $trends */
    private function kpisignalen(array $trends): void
    {
        foreach ($trends as $trend) {
            if (! $trend->heeftMetingen()) {
                continue;
            }

            // Voorbij de signaalwaarde: de organisatie heeft zelf vastgelegd dat
            // dit de grens is waaronder het niet meer aanvaardbaar is. Dit
            // vervangt geen bestaand signaal maar komt erbij (12d §6) — een
            // terugval en een overschrijding zijn twee verschillende dingen, en
            // een KPI kan vlak liggen en tóch onder de grens staan.
            if ($trend->status() === Kpitrend::STATUS_SLECHT) {
                $this->voegToe(
                    'kritiek',
                    $trend->definitie->naam.' staat voorbij de signaalwaarde',
                    'De vastgestelde signaalwaarde is '
                        .$this->waardeLabel($trend, $trend->laatste()?->signaalwaarde)
                        .', de streefwaarde '
                        .$this->waardeLabel($trend, $trend->laatste()?->streefwaarde).'.',
                    $this->uitkomstLabel($trend),
                );
            }

            if ($trend->laatsteStapIsAchteruit()) {
                $this->voegToe(
                    'let-op',
                    $trend->definitie->naam.' ging achteruit',
                    'De laatste meting staat lager dan de vorige. Een terugval is geen fout in '
                        .'de meting — het is de reden dat er gemeten wordt.',
                    $this->uitkomstLabel($trend),
                );
            }

            // Nadrukkelijk een positief signaal (blok 12 §4): een reeks die
            // inzakt en zich herstelt bewijst dat er gemeten wordt. Een reeks
            // die nooit beweegt bewijst dat niet.
            if (($herstel = $trend->herstelNaDip()) !== null) {
                $this->voegToe(
                    'goed',
                    $trend->definitie->naam.': ingezakt en hersteld',
                    'Zo\'n dip is bewijs dat de Check-fase daadwerkelijk meet, geen smet op '
                        .'het ISMS.',
                    round($herstel['dieptepunt']).'% → '.round($herstel['nu']).'%',
                );
            }
        }
    }

    /** Na hoeveel perioden zonder invoer een handmatige KPI als stilgevallen geldt. */
    private const STILTE_PERIODEN = 2;

    /**
     * Een handmatige KPI die niemand invult, valt stil — en op het trendpaneel is
     * dat niet te onderscheiden van "meet nog niet" (implementatie/12e §5).
     *
     * Dat is de voorspelbare manier waarop dit misgaat: hij wordt enthousiast
     * aangemaakt en na twee maanden vergeten. Een berekende KPI heeft dit
     * probleem niet — daar draait het commando.
     *
     * @param  list<Kpitrend>  $trends
     */
    private function stilgevallenHandmatigeKpis(array $trends): void
    {
        $grens = now()->subMonthsNoOverflow(self::STILTE_PERIODEN)->startOfMonth();

        $stil = collect($trends)
            ->filter(fn (Kpitrend $t) => $t->definitie->isHandmatig())
            ->filter(function (Kpitrend $t) use ($grens) {
                // Zonder meting telt de aanmaakdatum: een KPI die gisteren is
                // aangemaakt is niet stilgevallen, die is nog niet begonnen.
                $laatst = $t->laatste()?->gemeten_op ?? $t->definitie->created_at;

                return $laatst !== null && $laatst->lessThan($grens);
            })
            ->map(fn (Kpitrend $t) => $t->definitie->naam)
            ->values();

        if ($stil->isEmpty()) {
            return;
        }

        $this->voegToe(
            'let-op',
            $stil->count() === 1
                ? '1 handmatige KPI is stilgevallen'
                : $stil->count().' handmatige KPI\'s zijn stilgevallen',
            'Geen meetpunt in de afgelopen '.self::STILTE_PERIODEN.' perioden: '
                .$stil->implode(', ').'. Deze KPI\'s worden niet automatisch gemeten.',
            (string) $stil->count(),
        );
    }

    /**
     * De bewaking heeft stilgelegen (implementatie/00m §10).
     *
     * Eén regel, geen eigen scherm: er is niets te beheren aan een hartslag, en
     * een tabel met duizenden machineregels is voor een CISO geen informatie.
     * Wat er te dóén valt staat in de takenlijst — en juist daarom hangt dit
     * signaal aan de open taken en niet rechtstreeks aan de hartslag: staat er
     * geen taak, dan is het gat afgehandeld of viel het onder de meldgrens, en
     * hoort het dashboard erover te zwijgen.
     *
     * Het getal komt wél uit de hartslag zelf: het aantal dagen sinds het
     * stilste geplande commando voor het laatst iets van zich liet horen.
     */
    private function onderbrokenBewaking(): void
    {
        $open = Taak::query()
            ->whereIn('soort', ['kpi-meetpunt-gemist', 'bewaking-onderbroken'])
            ->whereIn('status', Taak::OPENSTAAND)
            ->count();

        if ($open === 0) {
            return;
        }

        $stilste = Systeemhartslag::query()
            ->select('taak_sleutel')
            ->selectRaw('MAX(gedraaid_op) as top')
            ->groupBy('taak_sleutel')
            ->pluck('top')
            ->min();

        $dagen = $stilste === null ? null : (int) Carbon::parse($stilste)->diffInDays(now());

        $this->voegToe(
            'kritiek',
            $dagen === null
                ? 'De bewaking heeft stilgelegen'
                : 'De bewaking heeft '.$dagen.' dagen niet gedraaid',
            'Geplande controles zijn niet uitgevoerd; wat daardoor niet meer te meten is, staat als '
                .($open === 1 ? 'taak' : $open.' taken').' in de takenlijst.',
            (string) $open,
        );
    }

    /**
     * Blok 12 §4 vraagt om het signaal *scoredaling zonder gekoppeld bewijs in
     * dezelfde periode*. Dat is vandaag niet te berekenen: het vraagt de drie
     * Act-metingen op de audit trail, en die zijn niet gebouwd.
     *
     * De regel staat er toch, met de reden erbij. Anders is het gat alleen in
     * het implementatieplan zichtbaar en niet voor wie het dashboard gebruikt —
     * en juist dit signaal is bedoeld om te zien of iemand zijn cijfers poetst.
     */
    private function nietTeBerekenen(): void
    {
        $this->voegToe(
            'neutraal',
            'Scoredaling zonder onderliggend bewijs — nog niet gemeten',
            'Dit signaal hoort er te zijn (blok 12 §4) maar vraagt metingen op de audit '
                .'trail die nog niet bestaan.',
            '—',
        );
    }

    /** Dezelfde schrijfwijze als `uitkomstLabel()`, voor een norm in plaats van een meting. */
    private function waardeLabel(Kpitrend $trend, ?float $waarde): string
    {
        if ($waarde === null) {
            return '—';
        }

        return $trend->waardeLabel($waarde);
    }

    private function uitkomstLabel(Kpitrend $trend): string
    {
        $laatste = $trend->laatste();
        $uitkomst = $laatste === null ? null : $trend->uitkomst($laatste);

        if ($uitkomst === null) {
            return '—';
        }

        return $trend->waardeLabel($uitkomst);
    }
}
