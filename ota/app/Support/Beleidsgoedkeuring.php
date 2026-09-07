<?php

namespace App\Support;

use App\Models\Beleidsversie;
use App\Models\Gebruiker;
use App\Models\Taak;
use Illuminate\Database\Eloquent\Builder;

/**
 * De taak die hoort bij "deze versie ligt ter goedkeuring"
 * (implementatie/05b §4).
 *
 * Tot 05b was de stap van `ter_goedkeuring` naar `actief` het enige moment in
 * blok 5 zonder signaal: de CISO bood aan, en de goedkeurder moest er uit
 * zichzelf achter komen. Erger nog dan geen taak was de onvindbaarheid — bij
 * een tweede versie onder een actieve eerste blijft de documentstatus `actief`
 * (`BeleidsversieObserver`, `actief` wint), dus ook het statusfilter op
 * `/beleid` wees er niet naar.
 *
 * Eén klasse voor twee aanroepers: de observer plant meteen bij de aanbieding,
 * `isms:genereer-taken` legt er 's nachts nog een keer de meetlat langs. Dat
 * tweede is geen dubbeling maar de reparatie van wat de observer niet kán zien:
 * wie goedkeurder is, is een eigenschap van de rollenmatrix en niet van de
 * versie, dus een rolwijziging komt hier nooit als save langs.
 */
final class Beleidsgoedkeuring
{
    public const SOORT = 'beleid-goedkeuring';

    /**
     * De actieve gebruikers die beleid mogen vaststellen: `goedkeuren` op
     * `beleid-maatregelbeheer` (sinds implementatie/01c uitsluitend Management,
     * niet de opsteller).
     *
     * Bewust een exacte match op het niveau en niet de ladder uit
     * `AppServiceProvider`: `goedkeuren` staat buiten die ladder en impliceert
     * alleen `lezen`, dus niemand "erft" het via `muteren`. Zou dat ooit
     * veranderen, dan verandert het hier ook — en dan hoort deze query mee te
     * bewegen, want een taak bij iemand die de knop niet heeft is erger dan
     * geen taak.
     *
     * @return list<int>
     */
    public static function goedkeurderIds(): array
    {
        return Gebruiker::query()
            ->where('status', 'actief')
            ->whereHas('rollen', fn (Builder $rol) => $rol
                ->whereHas('permissies', fn (Builder $permissie) => $permissie
                    ->where('niveau', 'goedkeuren')
                    ->whereHas('blok', fn (Builder $blok) => $blok->where('code', 'beleid-maatregelbeheer'))))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Wacht deze versie nog op een handtekening? Eén bron voor twee vragen: of
     * er een taak hoort te staan (hieronder), en of die taak met de hand
     * voltooid mag worden (`Beleidsversie::belemmeringVoorTaak()`). Uit elkaar
     * lopen zou betekenen dat een taak blijft staan die niemand mag sluiten,
     * of andersom.
     *
     * Een ingetrokken document telt niet mee: `intrekken()` laat een aangeboden
     * versie op `ter_goedkeuring` staan (alleen de actieve versie wordt
     * meegetrokken), en beleid vaststellen dat niet meer geldt, hoeft niemand.
     */
    public static function vraagtNogVaststelling(Beleidsversie $versie): bool
    {
        return $versie->wachtOpGoedkeuring()
            && ! ($versie->document?->isIngetrokken() ?? false);
    }

    /**
     * Brengt de openstaande goedkeuringstaken van één versie in
     * overeenstemming met de werkelijkheid. Idempotent: twee keer draaien
     * levert dezelfde taken op, met dezelfde deadline.
     *
     * @return int het aantal taken dat nu open staat voor deze versie
     */
    public static function synchroniseerTaken(Beleidsversie $versie): int
    {
        if (! self::vraagtNogVaststelling($versie)) {
            // Vastgesteld of ingetrokken: de vraag is beantwoord. Voltooien
            // en niet verwijderen — dat één goedkeurder klikte, sluit de taak
            // van álle goedkeurders, en dat het gebeurd is, is historie.
            TaakPlanner::voltooiVoorEntiteit($versie, self::SOORT);

            return 0;
        }

        $goedkeurderIds = self::goedkeurderIds();

        self::ruimVerweesdeTakenOp($versie, $goedkeurderIds);

        // Geen enkele actieve goedkeurder: dan één taak zonder eigenaar, met de
        // amber "geen eigenaar"-badge uit blok 7. Een versie die niemand kán
        // vaststellen is precies het geval dat niet stil mag blijven — en de
        // CISO ziet hem, want die heeft `muteren` op de takenengine.
        $eigenaren = $goedkeurderIds === [] ? [null] : $goedkeurderIds;

        foreach ($eigenaren as $eigenaarId) {
            TaakPlanner::planVoorEntiteit(
                $versie,
                self::SOORT,
                'Beleid vaststellen: '.$versie->auditOmschrijving(),
                $versie->goedkeurdeadline(),
                'beleid-maatregelbeheer',
                $eigenaarId,
                perEigenaar: true,
            );
        }

        return count($eigenaren);
    }

    /**
     * Verwijdert openstaande goedkeuringstaken die niet meer bij de huidige
     * verzameling goedkeurders horen: iemand die de rol kwijtraakte of
     * gedeactiveerd is, en de eigenaarloze noodtaak zodra er wél een
     * goedkeurder is.
     *
     * Bewust een directe delete en geen `deleteGeaudit()`, net als bij de
     * verweesde leesbevestigingstaken: dit zijn gegenereerde herinneringen,
     * geen registraties. De oorzaak — de gewijzigde rol — staat al in de audit
     * trail. Voltooide taken blijven staan; dat is historie.
     *
     * @param  list<int>  $goedkeurderIds
     */
    private static function ruimVerweesdeTakenOp(Beleidsversie $versie, array $goedkeurderIds): void
    {
        Taak::query()
            ->where('gekoppeld_entiteit_type', $versie->getMorphClass())
            ->where('gekoppeld_entiteit_id', $versie->getKey())
            ->where('soort', self::SOORT)
            ->whereIn('status', Taak::OPENSTAAND)
            // `whereNotIn` laat NULL-eigenaren staan (SQL-driewaardige logica),
            // dus de noodtaak moet er expliciet bij.
            ->when(
                $goedkeurderIds !== [],
                fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->whereNotIn('eigenaar_id', $goedkeurderIds)
                    ->orWhereNull('eigenaar_id')),
                fn (Builder $q) => $q->whereNotNull('eigenaar_id'),
            )
            ->delete();
    }
}
