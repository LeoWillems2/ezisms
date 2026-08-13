<?php

namespace App\Support;

use App\Models\KpiDefinitie;
use App\Models\Meting;
use Illuminate\Support\Collection;

/**
 * De meetreeks van één KPI, klaar om getoond te worden (implementatie/12c §3.1
 * en §3.3).
 *
 * Rekent niets opnieuw uit: `isms:meet-kpis` heeft teller en noemer vastgelegd
 * en die zijn onveranderlijk (blok 12 §2c). Deze klasse leidt daar alleen de
 * uitkomst, de richting en de vergelijkingsbasis uit af.
 */
final class Kpitrend
{
    /**
     * Over hoeveel maanden de delta op de KPI-tegel loopt.
     *
     * Bewust twaalf en niet één. Deze KPI's bewegen in maanden; tegen vorige
     * maand is de uitkomst meestal nul, en een strip vol nullen leert de lezer
     * niets. Twaalf maanden vangt bovendien het seizoen van de auditcyclus —
     * een interne audit in het najaar drukt de cijfers elk jaar op hetzelfde
     * moment.
     */
    public const DELTA_MAANDEN = 12;

    /** Aantal punten in de sparkline op de tegel. */
    public const SPARKLINE_PUNTEN = 12;

    /** @param Collection<int, Meting> $metingen oplopend op gemeten_op */
    private function __construct(
        public readonly KpiDefinitie $definitie,
        public readonly Collection $metingen,
    ) {}

    /** @param Collection<int, Meting> $metingen in willekeurige volgorde */
    public static function van(KpiDefinitie $definitie, Collection $metingen): self
    {
        return new self($definitie, $metingen->sortBy('gemeten_op')->values());
    }

    public function heeftMetingen(): bool
    {
        return $this->metingen->isNotEmpty();
    }

    public function laatste(): ?Meting
    {
        return $this->metingen->last();
    }

    /** De afgeleide uitkomst van een meetpunt: telling, gemiddelde in dagen, of percentage. */
    public function uitkomst(Meting $meting): ?float
    {
        return match (true) {
            // Een telling is de teller zelf; er valt niets af te leiden.
            $this->inAantal() => (float) $meting->teller,
            $this->inDagen() => $meting->gemiddelde(),
            default => $meting->percentage(),
        };
    }

    public function inDagen(): bool
    {
        return $this->definitie->eenheid === 'dagen';
    }

    public function inAantal(): bool
    {
        return $this->definitie->eenheid === 'aantal';
    }

    /**
     * Eén plek voor "hoe schrijf je een waarde van deze KPI op". Stond eerder op
     * acht plekken als een eigen `inDagen() ? … : …`; bij een derde eenheid
     * (12g) is dat acht keer dezelfde tak vergeten.
     */
    public function waardeLabel(?float $waarde): string
    {
        if ($waarde === null) {
            return '—';
        }

        return match (true) {
            $this->inAantal() => (string) (int) round($waarde),
            $this->inDagen() => number_format($waarde, 1, ',', '.').' d',
            default => round($waarde).'%',
        };
    }

    /** Een verschil draagt een andere eenheid dan een waarde: procentpunten, geen procenten. */
    public function deltaLabel(float $delta): string
    {
        return match (true) {
            $this->inAantal() => (string) (int) round($delta),
            $this->inDagen() => number_format($delta, 1, ',', '.').' d',
            default => round($delta).' pp',
        };
    }

    /**
     * De periodelengte bij een gebeurtenismeting. Bij een telling is dat geen
     * franje: het venster herstelt zichzelf na een gemiste run, dus twee
     * meetpunten kunnen ongelijke perioden beslaan en "14" betekent dan niet
     * hetzelfde (12g §3).
     */
    public function periodeLabel(Meting $meting): string
    {
        if ($meting->periode_tot === null) {
            return $meting->teller.' geteld';
        }

        return $meting->periode_van === null
            ? $meting->teller.' sinds het begin'
            : $meting->teller.' in '.(int) $meting->periode_van->diffInDays($meting->periode_tot).' dagen';
    }

    /** Het achtervoegsel voor `x-diagram.trendlijn`. */
    public function diagramEenheid(): string
    {
        return match (true) {
            $this->inAantal() => '',
            $this->inDagen() => ' d',
            default => '%',
        };
    }

    /**
     * Welke kant op is goed — een eigen vlag op de definitie (implementatie/12d
     * §1), niet langer afgeleid uit de eenheid.
     *
     * Die afleiding klopte zolang alleen 'dagen'-KPI's omlaag wilden, maar ze
     * koppelde de bétekenis van een beweging aan de meeteenheid. Bij een ratio
     * waarbij omlaag goed is — open bevindingen, open afwijkingen — rapporteert
     * dat verbetering als achteruitgang, en dat is erger dan geen signaal.
     */
    public function omlaagIsGoed(): bool
    {
        return $this->definitie->richting === 'omlaag';
    }

    /** @return list<float> de uitkomsten, oplopend in tijd */
    public function reeks(): array
    {
        return $this->metingen
            ->map(fn (Meting $m) => $this->uitkomst($m) ?? 0.0)
            ->all();
    }

    /** @return list<float> de staart van de reeks, voor de sparkline */
    public function sparkline(): array
    {
        return array_slice($this->reeks(), -self::SPARKLINE_PUNTEN);
    }

    /**
     * Het meetpunt waartegen de tegel vergelijkt: het laatste punt dat minstens
     * `DELTA_MAANDEN` oud is. Op datum en niet op index, want een reeks kan
     * gaten hebben (een gemiste maandelijkse run) en dan zou de dertiende rij
     * niet twaalf maanden terug liggen.
     *
     * Is de reeks korter dan dat, dan is de éérste meting de basis: "sinds we
     * meten" is een eerlijke vergelijking, een lege delta niet.
     */
    public function basis(): ?Meting
    {
        $laatste = $this->laatste();

        if ($laatste === null) {
            return null;
        }

        $grens = $laatste->gemeten_op->copy()->subMonthsNoOverflow(self::DELTA_MAANDEN);

        return $this->metingen
            ->filter(fn (Meting $m) => $m->gemeten_op->lessThanOrEqualTo($grens))
            ->last() ?? $this->metingen->first();
    }

    /** Het verschil met de vergelijkingsbasis, in procentpunten of dagen. */
    public function delta(): ?float
    {
        $laatste = $this->laatste();
        $basis = $this->basis();

        if ($laatste === null || $basis === null) {
            return null;
        }

        $nu = $this->uitkomst($laatste);
        $toen = $this->uitkomst($basis);

        return $nu === null || $toen === null ? null : round($nu - $toen, 1);
    }

    /**
     * De richting van de beweging, los van het teken: 'op' als het de goede
     * kant op gaat, 'neer' als het de verkeerde kant op gaat, 'vlak' als er
     * niets beweegt of er niets te vergelijken is.
     */
    public function richting(): string
    {
        $delta = $this->delta();

        if ($delta === null || abs($delta) < 0.05) {
            return 'vlak';
        }

        return ($delta < 0) === $this->omlaagIsGoed() ? 'op' : 'neer';
    }

    public const STATUS_GOED = 'goed';

    public const STATUS_AANDACHT = 'aandacht';

    public const STATUS_SLECHT = 'slecht';

    public const STATUS_ONBEPAALD = 'onbepaald';

    /**
     * De semafoorstand van één meetpunt tegen de streefwaarde die bij dát meetpunt
     * hoort
     * (implementatie/12d §2).
     *
     * Streef- en signaalwaarde komen van de **meetrij** en niet van de definitie.
     * Anders wordt de status van elk historisch punt live berekend tegen de
     * huidige streefwaarde, en kleuren twee jaar rode punten groen zodra iemand die
     * verlaagt (12 §2c).
     *
     * Zonder streefwaarde is de uitkomst `onbepaald` en nooit `goed`. Afwezigheid
     * van een streefwaarde mag niet lezen als "gehaald" — dat is de enige manier
     * waarop dit veld schade kan aanrichten, en het is de fout die vanzelf ontstaat als
     * je hier `?? 0` schrijft.
     */
    public function status(?Meting $meting = null): string
    {
        $meting ??= $this->laatste();

        if ($meting === null || $meting->streefwaarde === null) {
            return self::STATUS_ONBEPAALD;
        }

        $uitkomst = $this->uitkomst($meting);

        if ($uitkomst === null) {
            return self::STATUS_ONBEPAALD;
        }

        $gehaald = $this->omlaagIsGoed()
            ? $uitkomst <= $meting->streefwaarde
            : $uitkomst >= $meting->streefwaarde;

        if ($gehaald) {
            return self::STATUS_GOED;
        }

        // Wel een streefwaarde, geen ondergrens: dan is "niet gehaald" het
        // scherpste dat te zeggen valt. Rood zonder vastgestelde signaalwaarde
        // zou een oordeel zijn dat niemand heeft geveld.
        if ($meting->signaalwaarde === null) {
            return self::STATUS_AANDACHT;
        }

        $voorbijSignaal = $this->omlaagIsGoed()
            ? $uitkomst > $meting->signaalwaarde
            : $uitkomst < $meting->signaalwaarde;

        return $voorbijSignaal ? self::STATUS_SLECHT : self::STATUS_AANDACHT;
    }

    /** Dezelfde kleurbron als `Risico::scoreKleur()`, zodat de semafoor overal gelijk leest. */
    public static function statusKleur(string $status): string
    {
        return match ($status) {
            self::STATUS_GOED => 'green',
            self::STATUS_AANDACHT => 'amber',
            self::STATUS_SLECHT => 'red',
            default => 'zinc',
        };
    }

    /**
     * Richtingneutraal geformuleerd: "onder de norm" klopt niet bij een
     * omlaag-KPI. En zonder het woord "norm", want dat betekent in dit systeem
     * al ISO 27001 / NEN 7510.
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_GOED => 'Streefwaarde gehaald',
            self::STATUS_AANDACHT => 'Streefwaarde niet gehaald',
            self::STATUS_SLECHT => 'Voorbij de signaalwaarde',
            default => 'Geen streefwaarde vastgesteld',
        };
    }

    /** Daalde de laatste meting ten opzichte van de meting daarvoor? */
    public function laatsteStapIsAchteruit(): bool
    {
        if ($this->metingen->count() < 2) {
            return false;
        }

        $nu = $this->uitkomst($this->metingen[$this->metingen->count() - 1]);
        $vorige = $this->uitkomst($this->metingen[$this->metingen->count() - 2]);

        if ($nu === null || $vorige === null || $nu === $vorige) {
            return false;
        }

        return ($nu < $vorige) !== $this->omlaagIsGoed();
    }

    /** Hoeveel procentpunten een val en een herstel minstens moeten zijn. */
    private const DIP_DREMPEL = 20.0;

    /**
     * Een dip die zich herstelde: het "variantie is bewijs"-signaal uit 12c §3.2.
     * Levert `null` als de reeks er geen bevat.
     *
     * Bewust géén afkeuring: een reeks die inzakt en zich herstelt is bewijs dat
     * de Check-fase iets meet (blok 12 §4). Een reeks die nooit beweegt is dat
     * niet.
     *
     * Een dip vraagt **twee** bewegingen: een val vanaf een eerder hoger punt,
     * en daarna een herstel. Alleen "nu is hoger dan het laagste punt" is niet
     * genoeg — dat geldt voor elke oplopende opbouwcurve, en die zou dan
     * onterecht als herstelde dip verschijnen. De eerste metingen van een reeks
     * staan laag omdat er nog niets ópgebouwd was; dat is een start, geen val.
     *
     * @return array{dieptepunt: float, hoogtepunt: float, nu: float}|null
     */
    public function herstelNaDip(): ?array
    {
        // Twee redenen om over te slaan, en ze vallen niet samen: DIP_DREMPEL is
        // in procentpunten en zegt niets over een gemiddelde in dagen, én bij een
        // KPI waar omlaag goed is betekent een "dip" precies het omgekeerde.
        if ($this->metingen->count() < 3 || $this->inDagen() || $this->omlaagIsGoed()) {
            return null;
        }

        $reeks = $this->reeks();
        $nu = end($reeks);

        // Het laagste punt vóór de huidige stand.
        $eerder = array_slice($reeks, 0, -1);
        $dieptepunt = min($eerder);
        $dipIndex = (int) array_search($dieptepunt, $eerder, true);

        // Was er iets om vanaf te vallen? Zonder eerder hoogtepunt is dit een
        // startpunt en geen dip.
        $daarvoor = array_slice($reeks, 0, $dipIndex);

        if ($daarvoor === []) {
            return null;
        }

        $hoogtepunt = max($daarvoor);

        $gevallen = $hoogtepunt - $dieptepunt >= self::DIP_DREMPEL;
        $hersteld = $nu - $dieptepunt >= self::DIP_DREMPEL;

        return $gevallen && $hersteld
            ? ['dieptepunt' => $dieptepunt, 'hoogtepunt' => $hoogtepunt, 'nu' => $nu]
            : null;
    }
}
