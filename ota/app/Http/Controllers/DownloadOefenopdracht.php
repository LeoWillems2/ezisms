<?php

namespace App\Http\Controllers;

use App\Support\Kennisartikelen;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * De opdrachttekst van een oefening als bestand, om in een AI-assistent te plakken.
 *
 * Bewust **zonder** de autorisatiecheck `kennisartikel-downloaden` die de
 * Word-download van een artikel wel heeft. Tot deze route bestond, stond de
 * opdracht voluit in het artikel, en dat mag elke ingelogde gebruiker lezen en
 * dus kopiëren. Een download die strenger is dan wat de pagina al toonde,
 * beschermt niets en breekt de oefening voor een Auditor of een directielid.
 * Inhoudelijk is het ook geen gevoeliger materiaal: een verzonnen casus en
 * feiten over EzISMS zelf, niets over hoe deze organisatie is ingericht.
 *
 * Geen pandoc en geen conversie: de assistent leest markdown, en de lezer moet
 * precies de tekst krijgen die KennisbankOefeningTest tegen de code bewaakt.
 */
class DownloadOefenopdracht extends Controller
{
    public function __invoke(string $slug): StreamedResponse
    {
        // Via het register, zodat het profielfilter en "is dit een oefening"
        // dezelfde 404 opleveren als een onbekende slug.
        $pad = Kennisartikelen::opdrachtPad($slug);

        abort_if($pad === null, 404);

        $inhoud = (string) file_get_contents($pad);

        return response()->streamDownload(
            fn () => print ($inhoud),
            $slug.'-opdracht.md',
            [
                'Content-Type' => 'text/markdown; charset=utf-8',
                // Een verouderde kopie uit een cache is precies wat het artikel
                // de lezer afraadt; dan ook niet zelf in de cache laten hangen.
                'Cache-Control' => 'no-store',
            ],
        );
    }
}
