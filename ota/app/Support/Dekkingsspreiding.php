<?php

namespace App\Support;

use App\Models\Auditobject;
use Illuminate\Support\Collection;

/**
 * De verdeling van auditobjecten over de programmajaren van een cyclus
 * (implementatie/11b §5, losgetrokken in 11e §5).
 *
 * Stond als private methode in `BereidAuditcyclusVoor`, waar alleen het commando
 * erbij kon. Sinds het programmascherm dezelfde verdeling kan zetten, moet er één
 * definitie zijn: twee verdelingen die uit elkaar lopen maken de dekkingsplanning
 * afhankelijk van de weg waarlangs zij is ontstaan, en dat is precies wat een
 * auditor niet kan volgen.
 *
 * De regel: de **groep** bepaalt het jaar, niet het losse object. Verwante
 * onderwerpen — H4 Context, A.8 Technologisch — komen zo in dezelfde ronde aan
 * bod, en dat is wat een auditronde behapbaar maakt.
 */
final class Dekkingsspreiding
{
    /**
     * Groep i van de n groepen valt in programmajaar `1 + floor(i × jaren / n)`.
     *
     * De volgorde van de groepen komt uit `volgorde` en niet uit de binnenkomende
     * verzameling. Dat is geen detail: het commando haalt de objecten op
     * `orderBy('volgorde')` op en het scherm op `orderBy('groep')` — alfabetisch,
     * dus "10 Verbetering" vóór "4 Context" — en dan geeft dezelfde formule twee
     * verschillende verdelingen. De klasse bepaalt de volgorde zelf, zodat elke
     * aanroeper hetzelfde antwoord krijgt.
     *
     * @param  Collection<int, Auditobject>  $objecten
     * @return array<string, int> groep => programmajaar
     */
    public static function perGroep(Collection $objecten, int $jaren): array
    {
        $groepen = $objecten->sortBy('volgorde')->pluck('groep')->unique()->values();
        $aantal = max(1, $groepen->count());

        $verdeling = [];

        foreach ($groepen as $index => $groep) {
            $verdeling[$groep] = 1 + intdiv($index * $jaren, $aantal);
        }

        return $verdeling;
    }
}
