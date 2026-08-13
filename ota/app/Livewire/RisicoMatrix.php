<?php

namespace App\Livewire;

use App\Livewire\Concerns\LevertSchermkopie;
use App\Models\Risico;
use App\Support\Risicoverdeling;
use App\Support\Schermkopie;
use App\Support\Tolerantiematrixplaat;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Read-only tolerantiematrix van het risicoregister (implementatie/04b). Toont
 * per (kans, impact)-cel een teller en kleurt hem via `Risico::scoreKleur()` —
 * dezelfde banden als de lijst en het detail. Een cel aanklikken filtert de
 * risico's van die cel eronder; de selectie leeft in de URL zodat terugknop en
 * deellink haar behouden.
 */
#[Layout('components.layouts.app')]
class RisicoMatrix extends Component
{
    use LevertSchermkopie;

    #[Url]
    public ?int $kans = null;

    #[Url]
    public ?int $impact = null;

    /** Aparte selectie: de risico's buiten de matrix (kans of impact leeg). */
    #[Url]
    public bool $nietBeoordeeld = false;

    /** Selecteert (of deselecteert) een matrixcel. */
    public function selecteerCel(int $kans, int $impact): void
    {
        if ($this->kans === $kans && $this->impact === $impact) {
            $this->reset(['kans', 'impact']);

            return;
        }

        $this->kans = $kans;
        $this->impact = $impact;
        $this->nietBeoordeeld = false;
    }

    public function selecteerNietBeoordeeld(): void
    {
        $this->reset(['kans', 'impact']);
        $this->nietBeoordeeld = ! $this->nietBeoordeeld;
    }

    public function render()
    {
        $verdeling = $this->verdeling();

        return view('livewire.risico-matrix', [
            'schaal' => Risicoverdeling::SCHAAL,
            'tellers' => $verdeling->tellers,
            'drempel' => Risico::drempelwaarde(),
            'waarschuwing' => Risico::waarschuwingsdrempel(),
            'nietBeoordeeldAantal' => $verdeling->nietBeoordeeld,
            'geselecteerdeRisicos' => $this->geselecteerdeRisicos(),
        ]);
    }

    /**
     * De telling zelf staat in `Risicoverdeling`: het dashboardpaneel gebruikt
     * hem ook, en twee kopieën lopen uit elkaar (12c §3.4). Hier onthouden
     * binnen het request, zodat het scherm en de kopie ernaast gegarandeerd
     * dezelfde cijfers tonen en niet twee keer geteld wordt.
     */
    private ?Risicoverdeling $verdeling = null;

    private function verdeling(): Risicoverdeling
    {
        return $this->verdeling ??= Risicoverdeling::huidige();
    }

    protected function kopieBlok(): string
    {
        return 'risico-soa';
    }

    /**
     * De matrix als document: het plaatje, en eronder dezelfde cijfers als
     * tabel.
     *
     * **Geen filters, ook niet als er een cel geselecteerd is.** Die selectie
     * filtert op het scherm alleen de risicolijst *onder* de matrix; de matrix
     * zelf blijft volledig. Het document zou dus liegen als het "gefilterd op
     * kans 3 × impact 4" in de kop zette (12h §4). De risicolijst zelf hoort bij
     * het risicoregister en gaat hier niet mee.
     */
    protected function schermkopie(): Schermkopie
    {
        $verdeling = $this->verdeling();
        $schaal = Risicoverdeling::SCHAAL;
        $drempel = Risico::drempelwaarde();
        $waarschuwing = Risico::waarschuwingsdrempel();

        $kolommen = ['Impact'];
        for ($kans = 1; $kans <= $schaal; $kans++) {
            $kolommen[] = 'Kans '.$kans;
        }

        $rijen = [];
        for ($impact = $schaal; $impact >= 1; $impact--) {
            $rij = [(string) $impact];
            for ($kans = 1; $kans <= $schaal; $kans++) {
                $rij[] = (string) $verdeling->aantalIn($kans, $impact);
            }
            $rijen[] = $rij;
        }

        $toelichting = 'Het risicoregister als kans × impact, met per cel het aantal beoordeelde '
            .'risico\'s. De score is kans × impact; boven '.$drempel.' ligt een risico boven de '
            .'acceptatiedrempel, vanaf '.$waarschuwing.' vraagt het aandacht. In totaal zijn '
            .$verdeling->beoordeeld.' risico\'s beoordeeld, waarvan '.$verdeling->bovenDrempel()
            .' boven de drempel. ';

        $toelichting .= $verdeling->nietBeoordeeld === 0
            ? 'Alle risico\'s in het register zijn beoordeeld.'
            : $verdeling->nietBeoordeeld.' risico(\'s) zijn nog niet beoordeeld: bij die risico\'s '
                .'ontbreekt de kans of de impact, waardoor ze buiten de matrix vallen.';

        return new Schermkopie(
            scherm: 'Tolerantiematrix',
            kolommen: $kolommen,
            rijen: $rijen,
            totaalRijen: $schaal,
            toelichting: $toelichting,
            // Tellingen, geen namen: er staat geen persoon in dit document.
            metPersoonsgegevens: false,
            eenheid: 'impactniveaus',
            afbeelding: Tolerantiematrixplaat::teken($verdeling),
        );
    }

    /**
     * De risico's onder de matrix: die van de gekozen cel, of — bij de
     * niet-beoordeeld-selectie — de risico's zonder volledige beoordeling. Geen
     * selectie levert `null` op (dan verschijnt er geen lijst).
     */
    private function geselecteerdeRisicos()
    {
        if ($this->nietBeoordeeld) {
            return Risico::query()
                ->where(fn ($q) => $q->whereNull('kans_niveau')->orWhereNull('impact_niveau'))
                ->with('eigenaar')
                ->orderBy('titel')
                ->get();
        }

        if ($this->kans !== null && $this->impact !== null) {
            return Risico::query()
                ->where('kans_niveau', $this->kans)
                ->where('impact_niveau', $this->impact)
                ->with('eigenaar')
                ->orderBy('titel')
                ->get();
        }

        return null;
    }
}
