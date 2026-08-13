<?php

namespace App\Support;

/**
 * De normlabels als `$norm` in elke view (implementatie/00h §3).
 *
 * Bestaat om twee redenen, en de tweede is de reden dat dit geen
 * Blade-directive is:
 *
 * 1. Er hoeft geen `\App\Support\Normprofiel::label('bijlage')` in de templates
 *    te staan.
 * 2. **Het werkt ook binnen component-attributen.** Een `@norm('...')`-directive
 *    compileert niet in `label="…"` van een Flux-component, want die
 *    attribuutwaarde is een stringliteral en geen Blade-tekst. Twee
 *    mechanismen naast elkaar — een directive voor tekst en iets anders voor
 *    attributen — is precies het soort verschil dat je een halfjaar later niet
 *    meer weet.
 *
 * Onbekende sleutels gooien, want {@see Normprofiel::label()} doet dat: een
 * typefout in een blade hoort een fout te zijn en geen gat in de zin.
 *
 * Lui: de labels worden bij het renderen opgehaald en niet bij het delen, zodat
 * een profielwissel binnen één verzoek (tests) meteen doorwerkt.
 */
final class Normlabels
{
    public function __get(string $sleutel): string
    {
        return Normprofiel::label($sleutel);
    }

    public function __isset(string $sleutel): bool
    {
        return array_key_exists($sleutel, Normprofiel::labels());
    }
}
