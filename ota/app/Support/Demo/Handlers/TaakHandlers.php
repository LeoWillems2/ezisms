<?php

namespace App\Support\Demo\Handlers;

use App\Models\Taak;
use App\Models\Taaksjabloon;
use App\Support\Demo\DemoFixtureFout;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;
use Illuminate\Support\Carbon;

/**
 * De terugkerende taken uit `TaaksjabloonSeeder`.
 *
 * Deze klasse doet meer dan drie gebeurtenissen afhandelen: ze zorgt er ook voor
 * dat de taken die `isms:genereer-taken` elke maand aanmaakt daadwerkelijk
 * worden afgewerkt. Zonder die maandelijkse veegbeurt eindigt de demo met
 * ongeveer vijfenveertig openstaande en verlopen taken — een ISMS waarin
 * niemand ooit iets doet, en dan zegt de takenlijst niets meer.
 *
 * De tijdlijn stuurt daarop drie uitzonderingen: één taak wordt te laat
 * afgerond, één blijft liggen en verloopt, en één wordt expliciet afgerond.
 *
 * **Let op bij het lezen van de KPI's**: `reviewtaken_op_tijd` en
 * `reviewtaken_gem_overschrijding` tellen alleen taken met een `soort` — taken
 * die een bronblok aan een entiteit koppelt. Sjabloontaken hebben dat veld niet
 * en tellen dus niet mee. De knik in die KPI's komt in deze demo van de
 * risico-herbeoordelingen en de corrigerende maatregelen, niet van de
 * kwartaaltaken hieronder.
 */
final class TaakHandlers
{
    /** @var array<string, int> sjabloonnaam => aantal dagen te laat */
    private array $laatAfronden = [];

    /** @var array<string, bool> sjabloonnaam => de eerstvolgende taak blijft liggen */
    private array $laatLiggen = [];

    /** @var list<int> taak-id's die definitief blijven liggen */
    private array $blijftLiggen = [];

    /** @return array<string, callable> */
    public function register(): array
    {
        return [
            'taak_te_laat' => $this->taakTeLaat(...),
            'taak_verlopen' => $this->taakVerlopen(...),
            'taak_afronden' => $this->taakAfronden(...),
        ];
    }

    /**
     * De maandelijkse veegbeurt, aangeroepen door `Simulatie` ná de terugkerende
     * commando's. Alles wat over de deadline is, wordt afgerond op de dag dat het
     * moest — behalve wat de tijdlijn apart heeft gezet.
     */
    public function maandafsluiting(int $maand, Simulatie $sim): void
    {
        $taken = Taak::whereNotNull('taaksjabloon_id')
            ->whereIn('status', Taak::OPENSTAAND)
            ->whereDate('deadline', '<=', Carbon::today())
            ->with('sjabloon')
            ->get();

        if ($taken->isEmpty()) {
            return;
        }

        Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['taken-workflow-engine', 'muteren'])
            ->bij("M{$maand}/taken afwerken")
            ->doe(function () use ($taken) {
                foreach ($taken as $taak) {
                    $this->werkAf($taak);
                }
            });
    }

    private function werkAf(Taak $taak): void
    {
        if (in_array($taak->id, $this->blijftLiggen, true)) {
            return;
        }

        $naam = $taak->sjabloon?->naam;

        // Tegenslag 4: deze taak wordt niet opgepakt. Vanaf nu op id, want
        // `isms:verloop-taken` zet hem zo op 'verlopen' en die status telt nog
        // steeds als openstaand — zonder id zou een latere veegbeurt hem alsnog
        // afronden.
        if ($naam !== null && ($this->laatLiggen[$naam] ?? false)) {
            $this->blijftLiggen[] = $taak->id;
            unset($this->laatLiggen[$naam]);

            return;
        }

        $voltooidOp = $taak->deadline->copy()->addDays($this->laatAfronden[$naam] ?? 0);

        // Nog niet toe aan afronden: een taak die 23 dagen te laat wordt
        // afgerond, staat op de dag van de deadline nog gewoon open.
        if ($voltooidOp->isFuture()) {
            return;
        }

        $taak->update(['status' => 'voltooid', 'voltooid_op' => $voltooidOp]);
        unset($this->laatAfronden[$naam]);
    }

    /**
     * Tegenslag 1: de taak wordt niet vergeten, maar wel te laat opgepakt.
     *
     * Alleen de intentie wordt hier vastgelegd, op naam van het sjabloon en niet
     * op een concrete taak. Op het moment van de gebeurtenis hoeft de taak van
     * dat kwartaal namelijk nog niet te bestaan — `isms:genereer-taken` draait
     * aan het eind van de maand. De veegbeurt past de intentie toe zodra de taak
     * er is en de dag verstreken is.
     */
    private function taakTeLaat(array $g, int $maand, Simulatie $sim): void
    {
        $this->laatAfronden[$this->sjabloonnaam($g, $maand)] = (int) $g['dagen_te_laat'];
    }

    /** Tegenslag 4: de taak blijft liggen en wordt door `isms:verloop-taken` verlopen. */
    private function taakVerlopen(array $g, int $maand, Simulatie $sim): void
    {
        $this->laatLiggen[$this->sjabloonnaam($g, $maand)] = true;
    }

    /**
     * Een taak die het scenario expliciet noemt. Staat hij open, dan wordt hij nu
     * afgerond; bestaat hij nog niet, dan gebeurt dat in de veegbeurt zodra hij
     * er is — met `op_tijd` als vertraging nul.
     */
    private function taakAfronden(array $g, int $maand, Simulatie $sim): void
    {
        $naam = $this->sjabloonnaam($g, $maand);
        $taak = $this->eerstvolgende($naam);

        if ($taak === null) {
            $this->laatAfronden[$naam] = 0;

            return;
        }

        Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['taken-workflow-engine', 'muteren'])
            ->bij("M{$maand}/taak_afronden/{$naam}")
            ->doe(function () use ($g, $taak) {
                // `op_tijd` is een uitspraak van het scenario. Is de deadline al
                // verstreken, dan wint die uitspraak en wordt de afronding op de
                // deadline gedateerd — anders zou de demo iets anders tonen dan
                // het scenario beschrijft.
                $opTijd = $g['op_tijd'] ?? false;

                $taak->update([
                    'status' => 'voltooid',
                    'voltooid_op' => $opTijd && $taak->deadline->isPast() ? $taak->deadline : now(),
                ]);
            });
    }

    /**
     * Een sjabloon dat niet bestaat is een fixture-fout, geen randgeval: de
     * tijdlijn doet dan een uitspraak over werk dat nooit gepland wordt.
     */
    private function sjabloonnaam(array $g, int $maand): string
    {
        $naam = $g['taaksjabloon'];

        if (Taaksjabloon::where('naam', $naam)->doesntExist()) {
            throw DemoFixtureFout::bij("M{$maand}", "taaksjabloon '{$naam}' bestaat niet");
        }

        return $naam;
    }

    private function eerstvolgende(string $sjabloonnaam): ?Taak
    {
        return Taak::whereHas('sjabloon', fn ($q) => $q->where('naam', $sjabloonnaam))
            ->whereIn('status', Taak::OPENSTAAND)
            ->orderBy('deadline')
            ->first();
    }
}
