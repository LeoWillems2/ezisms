<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * De gegevens van de organisatie zelf: naam, adres, en wat een organisatie er
 * verder boven haar Verklaring van Toepasselijkheid en haar auditrapporten wil
 * hebben staan.
 *
 * **Eén vrij tekstveld en geen losse velden.** Welke soorten gegevens hier thuis
 * horen verschilt per organisatie — de een zet er een KvK-nummer en een
 * vestigingsadres neer, de ander een handelsnaam met een moederconcern erboven.
 * Een vast veldenschema zou die keuze voor iedereen maken en bij de helft niet
 * passen. Platte tekst met behouden regelovergangen laat de invuller de indeling
 * bepalen; er wordt niets als markdown gelezen, zodat een `*` of `#` in een
 * adresregel blijft staan waar hij staat.
 *
 * **Staat los van `ORGANISATIE` uit `.env`.** Dat is een installatie-instelling
 * met de korte naam, voor de zijbalk en de documentvoettekst — plekken waar één
 * regel moet passen. Deze tekst is inhoud van het ISMS en wordt in de
 * toepassing beheerd. Dat de naam daardoor op twee plaatsen staat is bewust:
 * het alternatief is de eerste regel van een vrij tekstveld als naam
 * interpreteren, en dat gaat mis zodra iemand met een kopregel begint.
 *
 * **Niet auditeerbaar**, net als de organisatie-eenheden eronder. Het is
 * stamgegeven, geen ISMS-besluit; elke adreswijziging in de audit trail zetten
 * levert ruis op zonder dat er een vraag mee beantwoord wordt.
 */
class Organisatieprofiel extends Model
{
    /** @see de migratie: enkelvoud, want er is er precies één. */
    protected $table = 'organisatieprofiel';

    /** @var list<string> */
    protected $fillable = ['gegevens'];

    /**
     * De ene rij, of een lege niet-opgeslagen rij als hij er nog niet is.
     *
     * Bewust géén `firstOrCreate`: dit wordt op elke paginaweergave aangeroepen,
     * en een leesactie die een rij wegschrijft maakt van een GET een mutatie.
     */
    public static function huidig(): self
    {
        return static::query()->first() ?? new static;
    }
}
