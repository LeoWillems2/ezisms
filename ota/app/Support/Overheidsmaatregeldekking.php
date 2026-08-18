<?php

namespace App\Support;

use App\Models\OverheidsmaatregelBeoordeling;

/**
 * Hoe ver de BIO-verplichtingen belegd zijn
 * (deelproducten/04b-bio-overheidsmaatregelen.md §6).
 *
 * Dit is het cijfer onder de In Control Verklaring en het eerste dat de RDI zal
 * vragen. Eén value object in plaats van tellers in de Livewire-component, om
 * dezelfde reden als {@see Maatregelverdeling}: de SoA-pagina, de export en straks
 * het dashboard moeten hetzelfde getal noemen.
 *
 * Twee dingen die deze klasse bewust *niet* meetelt:
 *
 * - **Verplichtingen onder een uitgesloten beheersmaatregel.** Is de
 *   beheersmaatregel niet van toepassing verklaard, dan hebben de verplichtingen
 *   eronder geen betekenis meer (04b §3.2). Ze verdwijnen niet — de historie blijft
 *   — maar ze drukken de dekkingsgraad niet omlaag.
 * - **Vervallen en verplaatste nummers.** Die dragen geen beoordelingsrij; de
 *   seeder maakt er geen aan.
 */
final class Overheidsmaatregeldekking
{
    private function __construct(
        public readonly int $totaal,
        public readonly int $belegd,
        public readonly int $deelsBelegd,
        public readonly int $nietBelegd,
        public readonly int $nietVanToepassing,
        public readonly int $onbeoordeeld,
        public readonly int $zonderRisicoanalyse,
        public readonly int $verouderd,
        public readonly int $buitenCbw,
    ) {}

    public static function huidige(): self
    {
        if (! Normprofiel::heeft('overheidsmaatregelen')) {
            return new self(0, 0, 0, 0, 0, 0, 0, 0, 0);
        }

        $basis = fn () => OverheidsmaatregelBeoordeling::query()
            ->join(
                'overheidsmaatregelen',
                'overheidsmaatregelen.id',
                '=',
                'overheidsmaatregel_beoordelingen.overheidsmaatregel_id',
            )
            ->join('soa_regels', 'soa_regels.id', '=', 'overheidsmaatregel_beoordelingen.soa_regel_id')
            // `van_toepassing` is null zolang de beheersmaatregel onbeslist is, en
            // dan tellen de verplichtingen eronder wél mee: onbeslist is geen
            // uitsluiting.
            ->where(fn ($q) => $q->whereNull('soa_regels.van_toepassing')
                ->orWhere('soa_regels.van_toepassing', true));

        $perStatus = $basis()
            ->selectRaw('overheidsmaatregel_beoordelingen.status as status, count(*) as aantal')
            ->groupBy('overheidsmaatregel_beoordelingen.status')
            ->pluck('aantal', 'status');

        $aantal = fn (string $status) => (int) ($perStatus[$status] ?? 0);

        return new self(
            totaal: (int) $perStatus->sum(),
            belegd: $aantal('belegd'),
            deelsBelegd: $aantal('deels_belegd'),
            nietBelegd: $aantal('niet_belegd'),
            nietVanToepassing: $aantal('niet_van_toepassing'),
            onbeoordeeld: $aantal('niet_beoordeeld'),
            zonderRisicoanalyse: $basis()
                ->where('overheidsmaatregel_beoordelingen.status', 'niet_van_toepassing')
                ->whereNull('overheidsmaatregel_beoordelingen.risicobehandeling_id')
                ->count(),
            /*
             * Beoordeeld vóórdat de verplichting voor het laatst wijzigde
             * (04b §3.4). Er is geen kolom voor: `overheidsmaatregelen.updated_at`
             * beweegt alleen als de seeder werkelijk iets veranderde, dus die
             * vergelijking ís het antwoord.
             *
             * `date()` bestaat in MySQL én SQLite, en die afronding op de dag is
             * hier juist: `laatst_beoordeeld_op` is een datum, en een seed en een
             * beoordeling op dezelfde dag mogen niet als "verouderd" gelden.
             */
            verouderd: $basis()
                ->whereNotNull('overheidsmaatregel_beoordelingen.laatst_beoordeeld_op')
                ->whereRaw('date(overheidsmaatregelen.updated_at) > '
                    .'overheidsmaatregel_beoordelingen.laatst_beoordeeld_op')
                ->count(),
            buitenCbw: $basis()->where('overheidsmaatregelen.cbw_reikwijdte', false)->count(),
        );
    }

    /** Het percentage belegd, of null als er niets te beleggen valt. */
    public function percentageBelegd(): ?int
    {
        $noemer = $this->totaal - $this->nietVanToepassing;

        return $noemer > 0 ? (int) round($this->belegd / $noemer * 100) : null;
    }

    /** Verplichtingen waar nog werk aan is: niet belegd, deels belegd of onbeoordeeld. */
    public function openstaand(): int
    {
        return $this->nietBelegd + $this->deelsBelegd + $this->onbeoordeeld;
    }

    /**
     * De signalen die op nul horen te staan vóór een verantwoording de deur uit
     * gaat, met hun label. Leeg is goed nieuws.
     *
     * @return array<string, int>
     */
    public function signalen(): array
    {
        return array_filter([
            'uitzondering zonder risicoanalyse' => $this->zonderRisicoanalyse,
            'beoordeling verouderd na een normwijziging' => $this->verouderd,
        ]);
    }
}
