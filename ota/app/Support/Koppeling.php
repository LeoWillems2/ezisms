<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Veel-op-veel-koppelingen wijzigen én dat vastleggen in de audit trail
 * (implementatie/06b).
 *
 * `Auditeerbaar` hangt aan `created`/`updated`/`deleted`. Een `sync()` raakt de
 * attributen van het model niet aan, dus die gebeurtenissen vuren niet en er
 * werd tot nu toe niets geschreven. Het gevolg is niet zichtbaar in het scherm:
 * een SoA-regel met één beleidsdocument ziet er hetzelfde uit of dat er gisteren
 * aan hing of twee jaar geleden — en een wéggehaalde koppeling liet helemaal
 * geen spoor na.
 *
 * Roep deze wikkel aan in plaats van de relatiemethodes rechtstreeks.
 * `KoppelingenWordenGelogdTest` bewaakt dat er in `app/Livewire` geen rauwe
 * `->sync(`/`->attach(`/`->detach(` meer staat; zonder die test is het gat er
 * bij het volgende koppelscherm stilzwijgend weer.
 */
final class Koppeling
{
    /**
     * Vervangt de koppelingen door precies deze verzameling.
     *
     * @param  iterable<int, mixed>  $ids
     * @return array{attached: list<mixed>, detached: list<mixed>, updated: list<mixed>}
     */
    public static function sync(BelongsToMany $relatie, string $veld, iterable $ids, ?Model $logOp = null): array
    {
        $resultaat = $relatie->sync($ids);

        self::log($relatie, $veld, $resultaat['attached'], $resultaat['detached'], $logOp);

        return $resultaat;
    }

    /**
     * Koppelt erbij zonder de rest los te laten (`syncWithoutDetaching`).
     *
     * @param  iterable<int, mixed>  $ids
     * @return array{attached: list<mixed>, detached: list<mixed>, updated: list<mixed>}
     */
    public static function koppelErbij(BelongsToMany $relatie, string $veld, iterable $ids, ?Model $logOp = null): array
    {
        $resultaat = $relatie->syncWithoutDetaching($ids);

        self::log($relatie, $veld, $resultaat['attached'], $resultaat['detached'], $logOp);

        return $resultaat;
    }

    /**
     * @param  array<string, mixed>  $attributen  pivotkolommen
     */
    public static function attach(
        BelongsToMany $relatie,
        string $veld,
        mixed $ids,
        array $attributen = [],
        ?Model $logOp = null,
    ): void {
        $relatie->attach($ids, $attributen);

        self::log($relatie, $veld, Arr::wrap($ids), [], $logOp);
    }

    /**
     * @param  mixed  $ids  null = alle koppelingen losmaken
     * @return int aantal verwijderde koppelrijen
     */
    public static function detach(BelongsToMany $relatie, string $veld, mixed $ids = null, ?Model $logOp = null): int
    {
        // Eerst kijken wat er écht aan hangt: `detach()` van een id dat er niet
        // aan zat is een no-op, en dat zou anders als verwijdering in de trail
        // belanden — een handeling loggen die niet heeft plaatsgevonden.
        $betrokken = $relatie->newPivotQuery()
            ->when($ids !== null, fn ($q) => $q->whereIn($relatie->getRelatedPivotKeyName(), Arr::wrap($ids)))
            ->pluck($relatie->getRelatedPivotKeyName())
            ->all();

        $aantal = $relatie->detach($ids);

        self::log($relatie, $veld, [], $betrokken, $logOp);

        return $aantal;
    }

    /**
     * Eén logregel per handeling, met de delta erin — niet één regel per rij.
     *
     * De normatieve scope van een auditronde koppelt 111 objecten in één klik;
     * honderdelf logregels maken de trail onleesbaar voor de vraag waar hij voor
     * bestaat.
     *
     * @param  list<mixed>  $gekoppeld
     * @param  list<mixed>  $ontkoppeld
     */
    private static function log(
        BelongsToMany $relatie,
        string $veld,
        array $gekoppeld,
        array $ontkoppeld,
        ?Model $logOp,
    ): void {
        // Opslaan zonder iets te wijzigen hoort geen ruis te maken; dezelfde
        // regel die `filterAuditVelden()` al hanteert.
        if ($gekoppeld === [] && $ontkoppeld === []) {
            return;
        }

        // De regel hangt aan het model waar de gebruiker mee bezig was. Bij een
        // koppeling die vanaf het scherm van een ánder model wordt gelegd, geeft
        // de aanroeper dat model mee (06b §4).
        $doel = $logOp ?? $relatie->getParent();

        if (! method_exists($doel, 'schrijfAuditregel')) {
            throw new RuntimeException(sprintf(
                'Koppelingen op %s kunnen niet worden gelogd: het model gebruikt de Auditeerbaar-trait niet. '
                .'Geef met $logOp het model mee waar het scherm over gaat.',
                $doel::class,
            ));
        }

        $doel->schrijfAuditregel(
            'gewijzigd',
            oud: $ontkoppeld === [] ? null : [$veld => self::samenvatting('ontkoppeld', $relatie, $ontkoppeld)],
            nieuw: $gekoppeld === [] ? null : [$veld => self::samenvatting('gekoppeld', $relatie, $gekoppeld)],
        );
    }

    /**
     * Namen, geen id's: "asset #3" is waardeloos voor een auditor. Het aantal
     * staat vooraan zodat het blijft staan als het scherm de regel afkapt — de
     * opslag kapt niets af, de weergave mag dat wel.
     *
     * @param  list<mixed>  $ids
     */
    private static function samenvatting(string $werkwoord, BelongsToMany $relatie, array $ids): string
    {
        $namen = $relatie->getRelated()->newQuery()
            ->whereKey($ids)
            ->get()
            ->map(fn (Model $rij) => self::omschrijvingVan($rij))
            ->sort()
            ->values();

        return count($ids).' '.$werkwoord.($namen->isEmpty() ? '' : ': '.$namen->implode(', '));
    }

    /**
     * Spiegelt `Auditeerbaar::auditOmschrijving()` voor modellen die de trait
     * niet dragen — een issue of een organisatie-eenheid is zelf niet
     * auditeerbaar maar wordt wel gekoppeld. `omschrijving` staat er extra bij:
     * een issue heeft geen naam of titel, en "Issue #4" zegt niets.
     */
    private static function omschrijvingVan(Model $rij): string
    {
        if (method_exists($rij, 'auditOmschrijving')) {
            return $rij->auditOmschrijving();
        }

        foreach (['naam', 'titel', 'omschrijving'] as $veld) {
            if (filled($rij->getAttribute($veld))) {
                return (string) $rij->getAttribute($veld);
            }
        }

        return class_basename($rij).' #'.$rij->getKey();
    }
}
