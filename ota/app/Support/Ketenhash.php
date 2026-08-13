<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * De hash van één regel in de audit trail (implementatie/06c §2).
 *
 * Elke logregel draagt de hash van zijn voorganger, zodat het verwijderen,
 * wijzigen of tussenvoegen van een regel de keten breekt. Deze klasse doet
 * alleen het rekenwerk; {@see Audittrailketen} legt de keten aan en loopt hem na.
 *
 * **De canonieke vorm is het hele punt.** De hash moet vandaag én over drie jaar
 * dezelfde uitkomst geven, op sqlite in de tests en op MySQL in productie. Dat
 * gaat niet vanzelf:
 *
 * - MySQL normaliseert `json`-kolommen: sleutels worden geherordend en witruimte
 *   verdwijnt. Wie de opgeslagen bytes hasht, krijgt een andere uitkomst per
 *   database — en zelfs een andere na een dump-en-restore.
 * - `tijdstip` komt er als string uit en gaat er als Carbon in.
 * - De sleutelvolgorde in `oude_waarde`/`nieuwe_waarde` komt van de schrijver.
 *
 * Daarom niet de opgeslagen bytes maar een eigen vorm, aan beide kanten van de
 * streep identiek opgebouwd: vaste veldvolgorde, recursief gesorteerde sleutels,
 * en de tijd in één vast formaat.
 *
 * **Wijzig deze klasse niet zonder het te bedoelen.** `KetenhashTest` legt één
 * regel vast met een letterlijk opgeschreven hash. Verandert de uitkomst, dan
 * faalt die test — en dan is dat een bewuste versiestap, want elke bestaande
 * installatie moet daarna opnieuw verzegeld worden.
 */
final class Ketenhash
{
    /**
     * De velden die meetellen, in vaste volgorde.
     *
     * `id` zit er bewust niet in. Hij is niet nodig — de plaats in de keten ligt
     * al vast via `vorige_hash`, en een verwijderde of ingevoegde regel breekt de
     * schakel ongeacht de nummering. Hem wél meenemen zou betekenen dat de hash
     * pas ná de insert te berekenen is, en dat vraagt een UPDATE op een
     * append-only regel.
     */
    private const VELDEN = [
        'tijdstip', 'gebruiker_id', 'gebruiker_naam', 'blok_naam',
        'entiteit_type', 'entiteit_id', 'entiteit_omschrijving',
        'actie', 'oude_waarde', 'nieuwe_waarde',
    ];

    /** Velden die als geheel getal of null tellen, ongeacht wat de driver teruggeeft. */
    private const GETALVELDEN = ['gebruiker_id', 'entiteit_id'];

    /** Velden met JSON-inhoud: gesorteerd vergeleken, niet als tekst. */
    private const JSONVELDEN = ['oude_waarde', 'nieuwe_waarde'];

    /**
     * @param  array<string, mixed>|object  $regel  attributen of een databaserij
     */
    public static function van(array|object $regel, ?string $vorigeHash): string
    {
        return hash('sha256', self::canoniek($regel, $vorigeHash));
    }

    /**
     * De vorm waar de hash over gaat. Publiek omdat een falende controle
     * begrijpelijk moet zijn: wie wil weten wáárom twee hashes verschillen, legt
     * deze twee strings naast elkaar.
     *
     * @param  array<string, mixed>|object  $regel
     */
    public static function canoniek(array|object $regel, ?string $vorigeHash): string
    {
        $regel = (array) $regel;
        $canoniek = [];

        foreach (self::VELDEN as $veld) {
            $waarde = $regel[$veld] ?? null;

            $canoniek[$veld] = match (true) {
                in_array($veld, self::GETALVELDEN, true) => $waarde === null ? null : (int) $waarde,
                in_array($veld, self::JSONVELDEN, true) => self::gesorteerd(self::alsArray($waarde)),
                $veld === 'tijdstip' => self::tijdstip($waarde),
                default => (string) $waarde,
            };
        }

        $canoniek['vorige_hash'] = $vorigeHash;

        return (string) json_encode($canoniek, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Seconden, geen microseconden: MySQL bewaart een `timestamp` zonder
     * fractie, dus wat eruit komt zou anders nooit gelijk zijn aan wat erin ging.
     */
    private static function tijdstip(mixed $waarde): string
    {
        if ($waarde === null) {
            return '';
        }

        return ($waarde instanceof Carbon ? $waarde : Carbon::parse((string) $waarde))
            ->format('Y-m-d H:i:s');
    }

    /** @return array<string, mixed>|null */
    private static function alsArray(mixed $waarde): ?array
    {
        if ($waarde === null || $waarde === '') {
            return null;
        }

        if (is_string($waarde)) {
            $waarde = json_decode($waarde, true);
        }

        return is_array($waarde) ? $waarde : null;
    }

    /**
     * Recursief op sleutel sorteren — dit is wat de keten bestand maakt tegen de
     * herordening van MySQL. Lijsten houden hun volgorde: daar ís de volgorde de
     * betekenis.
     */
    private static function gesorteerd(mixed $waarde): mixed
    {
        if (! is_array($waarde)) {
            return $waarde;
        }

        $waarde = array_map(self::gesorteerd(...), $waarde);

        if (! array_is_list($waarde)) {
            ksort($waarde);
        }

        return $waarde;
    }
}
