<?php

namespace App\Support;

use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Gebruiker;
use App\Models\Leesbevestiging;
use App\Models\Raadpleging;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Leesbevestigingen per persoon (implementatie/05 §6, §14).
 *
 * Losgetrokken uit `BeleidsdocumentDetail` (wie wel en wie niet bevestigde) en
 * `BeleidsdocumentenOverzicht` (wat de gebruiker zelf nog moet bevestigen) voor
 * de assistenttool `training-en-bewustzijn` (implementatie/14b §4.3).
 *
 * De lijst per document is auditmateriaal en alleen voor wie
 * `Recordscope::magAllesZien('beleid-maatregelbeheer')` heeft; die check doet de
 * aanroeper, net als het scherm.
 */
final class Leesbevestigingslijst
{
    /**
     * @param  Collection<int, Leesbevestiging>  $bevestigingen  met `gebruiker` geladen
     * @param  Collection<int, Gebruiker>  $nietBevestigd  doelgroepleden zonder bevestiging, op naam
     * @param  array<int, bool>  $zonderRaadpleging  gebruiker-id => bevestigd zonder het bestand op te halen
     */
    private function __construct(
        public readonly Collection $bevestigingen,
        public readonly Collection $nietBevestigd,
        public readonly array $zonderRaadpleging,
    ) {}

    /** Een lege lijst: geen actieve versie, of geen volledige inzage. */
    public static function leeg(): self
    {
        return new self(collect(), collect(), []);
    }

    public static function voor(Beleidsdocument $document, Beleidsversie $actieve): self
    {
        $bevestigingen = $actieve->bevestigingen()->with('gebruiker')->get();

        return new self(
            $bevestigingen,
            // Alleen de doelgroep telt als "nog niet bevestigd": iemand buiten
            // de afdeling hoeft niet te bevestigen (§6).
            Gebruiker::whereIn('id', $document->doelgroepGebruikerIds())
                ->whereNotIn('id', $actieve->bevestigingen()->pluck('gebruiker_id'))
                ->orderBy('naam')
                ->get(),
            self::bevestigdZonderRaadpleging($bevestigingen, $actieve->bewijsstuk_id),
        );
    }

    /**
     * Actieve versies die deze gebruiker nog moet bevestigen: documenten die op
     * zijn afdeling gericht zijn, binnen de `zichtbaar()`-scope van de lijst.
     * Zonder afdeling is er geen doelgroep om in te vallen, dus niets.
     *
     * @return Builder<Beleidsversie>
     */
    public static function openstaandVoor(Gebruiker $gebruiker): Builder
    {
        $afdelingId = $gebruiker->organisatie_eenheid_id;

        return Beleidsversie::where('status', 'actief')
            ->when($afdelingId === null, fn ($q) => $q->whereRaw('1 = 0'))
            ->whereHas('document', fn ($q) => $q->zichtbaar()
                ->where('leesbevestiging_vereist', true)
                ->whereHas('afdelingen', fn ($a) => $a->where('organisatie_eenheden.id', $afdelingId)))
            ->whereDoesntHave('bevestigingen', fn ($q) => $q->where('gebruiker_id', $gebruiker->id));
    }

    /**
     * Bevestigingen waarvan niet blijkt dat de bevestiger het bestand had.
     *
     * Bewust geen blokkade vóór de knop maar een signaal achteraf: iemand die
     * het beleid in een teamsessie of op papier heeft gelezen, bevestigt terecht
     * zonder download, en een verplichte download zou daar een vals negatief
     * van maken. Het getal is stuurinformatie voor de CISO — een leesbevestiging
     * blijft een verklaring, geen meting (implementatie/05 §14).
     *
     * @param  Collection<int, Leesbevestiging>  $bevestigingen
     * @return array<int, bool> gebruiker_id => bevestigd zonder het bestand op te halen
     */
    private static function bevestigdZonderRaadpleging(Collection $bevestigingen, ?int $bewijsstukId): array
    {
        $eerste = Raadpleging::eersteRaadplegingPerGebruiker($bewijsstukId);

        return $bevestigingen
            ->mapWithKeys(fn (Leesbevestiging $b) => [
                // Ophalen ná het bevestigen telt niet: dan is er bevestigd op
                // het moment dat het document nog niet was opgehaald.
                $b->gebruiker_id => ! isset($eerste[$b->gebruiker_id])
                    || $eerste[$b->gebruiker_id]->greaterThan($b->bevestigd_op),
            ])
            ->all();
    }
}
