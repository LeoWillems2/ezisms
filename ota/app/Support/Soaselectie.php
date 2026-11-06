<?php

namespace App\Support;

use App\Models\Maatregel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * De maatregelen zoals de SoA ze toont: de query, de filters en de volgorde
 * van `SoaOverzicht`, op één plek.
 *
 * Bestaat sinds de assistenttoegang (implementatie/14b §4.3): de tool
 * `maatregelstatus` hoort precies dezelfde rijen en dezelfde ingeladen
 * relaties te zien als het scherm. Stond tot dan inline in de component; een
 * tweede kopie in de tool was gaan afwijken op juist het detail dat ertoe
 * doet, het beleid dat alleen telt als het actief is.
 *
 * Geen record-scoping: op `risico-soa` heeft de Medewerker geen recht, en wie
 * het wel heeft, ziet de hele SoA.
 */
final class Soaselectie
{
    /** @return Builder<Maatregel> */
    public static function query(string $thema = '', string $status = '', bool $alleenOnbeslist = false): Builder
    {
        return Maatregel::query()
            // Blok 5: alleen ACTIEF beleid telt als onderbouwing. Een concept
            // of ingetrokken document zou de dekking te rooskleurig maken.
            ->with(['soaRegel.beleidsdocumenten' => fn ($q) => $q->where('status', 'actief')])
            // Plan 04c: de restrisico-rollup per control leidt af uit de
            // gekoppelde behandelingen (max restrisico + distinct risico's).
            ->with('soaRegel.risicobehandelingen')
            // Alleen in een BIO-installatie: voor de kolom, de uitklapbare
            // regellaag en de bijlage bij de schermkopie. 93 maatregelen die elk
            // hun verplichtingen opzoeken is anders 93 queries — en zonder
            // `.overheidsmaatregel` erbij nog eens 118, want de nummers zitten
            // niet in de beoordeling maar in de referentiedata erachter.
            ->when(Normprofiel::heeft('overheidsmaatregelen'), fn ($q) => $q->with([
                'soaRegel.overheidsmaatregelBeoordelingen.overheidsmaatregel',
                'soaRegel.overheidsmaatregelBeoordelingen.bewijsKoppelingen',
            ]))
            ->when($thema !== '', fn ($q) => $q->where('thema', $thema))
            ->when($status !== '', fn ($q) => $q->whereHas(
                'soaRegel',
                fn ($sub) => $sub->where('implementatiestatus', $status)
            ))
            ->when($alleenOnbeslist, fn ($q) => $q->whereHas(
                'soaRegel',
                fn ($sub) => $sub->whereNull('van_toepassing')
            ));
    }

    /**
     * Numeriek sorteren op '5.10' vs '5.9' vergt database-specifieke SQL
     * (SUBSTRING_INDEX/CAST); bij 93 rijen is sorteren in PHP simpeler en werkt
     * het op zowel MySQL als de sqlite-testdatabase.
     *
     * @param  Collection<int, Maatregel>  $maatregelen
     * @return Collection<int, Maatregel>
     */
    public static function sorteer(Collection $maatregelen): Collection
    {
        return $maatregelen
            ->sortBy(function (Maatregel $maatregel) {
                [$hoofdstuk, $sub] = array_pad(explode('.', $maatregel->annex_a_referentie, 2), 2, '0');

                return (int) $hoofdstuk * 1000 + (int) $sub;
            })
            ->values();
    }

    /** @return Collection<int, Maatregel> */
    public static function maatregelen(string $thema = '', string $status = '', bool $alleenOnbeslist = false): Collection
    {
        return self::sorteer(self::query($thema, $status, $alleenOnbeslist)->get());
    }
}
