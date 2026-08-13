<?php

namespace App\Http\Controllers;

use App\Support\Documentvoettekst;
use App\Support\Kennisartikelen;
use App\Support\Pandoc;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Een kennisartikel als Word-document meenemen. Alleen voor de CISO
 * (`kennisartikel-downloaden`, zie AppServiceProvider): lezen mag iedereen,
 * maar een bestand dat het systeem verlaat is een andere handeling dan een
 * pagina lezen — en de kennisbank beschrijft hoe deze installatie is ingericht.
 *
 * De conversie loopt via dezelfde {@see Pandoc}-wikkel als de schermkopie voor
 * de auditor: markdown erin, `.docx` eruit, via stdin/stdout zodat er onderweg
 * geen kopie op schijf staat.
 *
 * Geen registratie zoals bij een bewijsstuk of een schermkopie: dit is
 * meegeleverde documentatie uit de repo, geen bewijsmateriaal van deze
 * organisatie. Er is dus ook niets aan af te lezen over wat de organisatie doet.
 */
class DownloadKennisartikel extends Controller
{
    public function __invoke(string $slug, Pandoc $pandoc): StreamedResponse
    {
        // Via het register en niet rechtstreeks op de map: dat filtert op het
        // actieve normprofiel, dus het artikel van het andere profiel geeft hier
        // 404 — net als de pagina zelf. Zonder dat zou de download een
        // achterdeur zijn om de tekst van het andere profiel op te halen.
        // `isDownloadbaar()` dekt ook het bestaan van de slug, inclusief het
        // profielfilter: hetzelfde antwoord voor "bestaat niet" en "hier valt
        // geen bruikbaar document van te maken". Dezelfde bron als de knop, dus
        // de twee kunnen niet uit elkaar lopen.
        abort_unless(Kennisartikelen::isDownloadbaar($slug), 404);

        $meta = Kennisartikelen::metadata($slug);
        $markdown = Kennisartikelen::inhoud($slug);

        abort_if($meta === null || $markdown === null, 404);

        // `inhoud()` haalt een eigen H1 weg, want de pagina toont de titel al
        // apart. Een los document heeft die kop wél nodig, en dan meteen de
        // titel uit het register: dat is de titel die de lezer in de lijst zag,
        // en twee artikelen dragen in hun bestand helemaal geen H1.
        $markdown = '# '.$meta['titel']."\n\n".$markdown;

        // Vóór de conversie, zodat een ontbrekende pandoc een leesbare melding
        // geeft in plaats van een lege download of een stacktrace.
        abort_if(
            ! $pandoc->beschikbaar(),
            503,
            'Het Word-document kan niet worden gemaakt: pandoc ontbreekt op deze server.'
        );

        try {
            // Zonder organisatienaam, anders leest de meegeleverde documentatie
            // als een document van de organisatie zelf — zie de kop hierboven.
            $inhoud = $pandoc->naarDocx($markdown, Documentvoettekst::voorKennisartikel());
        } catch (Throwable $fout) {
            // Een knop die stil niets doet is erger dan geen knop — dezelfde
            // regel als bij de schermkopie (implementatie/12h §7).
            abort(503, 'Het Word-document kon niet worden gemaakt.');
        }

        return response()->streamDownload(
            fn () => print ($inhoud),
            $slug.'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }
}
