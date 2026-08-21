<?php

namespace App\Support;

/**
 * Zoekt in een toetsbestand naar bronnen die de browser bij een andere host zou
 * ophalen (implementatie/10b §6).
 *
 * Dit is een waarschuwing en geen keuring. De blokkade zit in de CSP van
 * `Toetsrespons`; deze scan bestaat alleen zodat de Administrator het merkt vóór
 * hij de toets uitzet, in plaats van de deelnemer erna. Daarom mag hij
 * onvolledig zijn: een URL die JavaScript zelf samenstelt wordt hier gemist en
 * door de CSP alsnog geblokkeerd.
 *
 * Bewust niet meegeteld: `<a href>`. Een hyperlink laadt niets — die volgt pas
 * als iemand klikt. Een scan die elke verwijzing aanstreept, wordt weggeklikt.
 */
final class ExterneBronnen
{
    /** Elementen die hun `src` daadwerkelijk ophalen. */
    private const LADENDE_ELEMENTEN = 'script|img|iframe|source|video|audio|embed|track|object';

    /**
     * De unieke hosts waar dit bestand iets vandaan haalt, gesorteerd.
     *
     * @return list<string>
     */
    public static function hosts(string $html): array
    {
        $verwijzingen = [];

        // src="…" op elementen die het ook echt ophalen.
        preg_match_all(
            '/<(?:'.self::LADENDE_ELEMENTEN.')\b[^>]*?\bsrc\s*=\s*["\']([^"\']+)/i',
            $html, $treffers);
        $verwijzingen = array_merge($verwijzingen, $treffers[1]);

        // href="…" alleen op <link>: dat is een stylesheet of een preload.
        preg_match_all('/<link\b[^>]*?\bhref\s*=\s*["\']([^"\']+)/i', $html, $treffers);
        $verwijzingen = array_merge($verwijzingen, $treffers[1]);

        // url(…) in CSS — lettertypen en achtergrondplaatjes.
        preg_match_all('/url\(\s*["\']?\s*([^)"\'\s]+)/i', $html, $treffers);
        $verwijzingen = array_merge($verwijzingen, $treffers[1]);

        $hosts = [];

        foreach ($verwijzingen as $verwijzing) {
            // Absoluut (https://host/…) of protocol-relatief (//host/…). Al het
            // andere is een pad in het bestand zelf, en `data:` is juist de
            // route die we willen.
            if (preg_match('#^(?:https?:)?//([^/?\#"\'\s]+)#i', trim($verwijzing), $m)) {
                $hosts[strtolower($m[1])] = true;
            }
        }

        $hosts = array_keys($hosts);
        sort($hosts);

        return $hosts;
    }
}
