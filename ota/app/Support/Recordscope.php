<?php

namespace App\Support;

use Illuminate\Support\Facades\Gate;

/**
 * Record-scoping voor blokken waar de Medewerker `uitvoeren` heeft.
 *
 * De niveau-ladder alleen volstaat daar niet: `uitvoeren` impliceert `lezen`,
 * dus een route-check op `lezen` geeft de Medewerker inzage in andermans
 * gegevens. `muteren` (CISO), `exporteren` (Auditor) of `goedkeuren`
 * (Management) onderscheidt die rollen wel — vandaar dat de Auditor
 * `exporteren` krijgt op elk blok dat deze scope gebruikt. Zonder dat zou het
 * onderscheid alleen negatief te maken zijn ("heeft lezen maar niet
 * uitvoeren"), en dat klapt stilzwijgend om zodra de rechtenmatrix verandert.
 *
 * De generieke Gate blijft ongewijzigd; dit is een extra scope erbovenop, geen
 * vervanging — het record-scoped patroon dat `deelproducten/11` §4 ook voor de
 * auditor-onafhankelijkheid voorziet.
 */
final class Recordscope
{
    /**
     * Volledige inzage in dit blok, in plaats van alleen de eigen rijen.
     *
     * `goedkeuren` telt sinds implementatie/01c mee: dat niveau staat niet meer
     * in de ladder, dus zonder dit signaal zou een Management-lid alleen zijn
     * eigen incidentmeldingen zien — en daarmee de §9.3-input missen die hij
     * geacht wordt te beoordelen. Wie mag vaststellen moet het geheel zien.
     */
    public static function magAllesZien(string $blokCode): bool
    {
        return Gate::allows('heeft-niveau', [$blokCode, 'muteren'])
            || Gate::allows('heeft-niveau', [$blokCode, 'goedkeuren'])
            || Gate::allows('heeft-niveau', [$blokCode, 'exporteren']);
    }
}
