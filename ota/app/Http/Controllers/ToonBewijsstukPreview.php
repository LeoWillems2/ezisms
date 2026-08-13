<?php

namespace App\Http\Controllers;

use App\Models\Bewijsstuk;
use App\Models\Raadpleging;
use App\Support\Bewijstoegang;
use App\Support\Documentpreview;
use App\Support\PreviewNietBeschikbaar;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Preview van een bewijsstuk zonder het te downloaden. Deelt de leespoort met de
 * download ({@see Bewijstoegang}) en telt, net als een download, als
 * **raadpleging** (§14): wie de preview opent, heeft het document gezien — het
 * ontkracht dus het signaal "bevestigd zonder het bestand op te halen".
 *
 * Twee wegen, afhankelijk van het bestandstype:
 *
 * - **Conversie** (RTF, DOCX, ODT, Markdown): pandoc maakt er HTML van, die
 *   wordt gesaneerd tot een allowlist en geserveerd met een strikte
 *   Content-Security-Policy — geen scripts, geen externe bronnen. Dat zijn de
 *   drie lagen tegen wat er uit een geprepareerd document kan komen.
 * - **Inline** (PDF): het bestand zelf, met een expliciet content-type en
 *   `nosniff`, zodat de browser zijn eigen viewer opent en niets anders. Een
 *   conversie naar HTML zou de opmaak weggooien, en bij beleid is de opmaak
 *   onderdeel van het document.
 */
class ToonBewijsstukPreview extends Controller
{
    /**
     * Geen scripts, geen externe bronnen; alleen inline stijl (voor de opmaak
     * van de preview zelf) en data:-plaatjes. `base-uri`/`form-action` op 'none'
     * sluiten de laatste omleidingsroutes af.
     */
    private const CSP = "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; font-src data:; base-uri 'none'; form-action 'none'";

    public function __invoke(Bewijsstuk $bewijsstuk, Documentpreview $preview): Response|StreamedResponse
    {
        abort_unless(Bewijstoegang::magLezen($bewijsstuk), 403);
        abort_unless($preview->isPreviewbaar($bewijsstuk), 404);
        abort_unless(
            Storage::disk(Bewijsstuk::DISK)->exists($bewijsstuk->opslaglocatie_referentie),
            404
        );

        if (($type = $bewijsstuk->inlineType()) !== null) {
            return $this->inline($bewijsstuk, $type);
        }

        try {
            $inhoud = $preview->html($bewijsstuk);
        } catch (PreviewNietBeschikbaar) {
            // Geen preview (pandoc ontbreekt/te oud, of conversie mislukt), maar
            // ook geen 500: de download blijft de echte weg. Geen raadpleging,
            // want er is niets gezien.
            return response()
                ->view('bewijs.preview-niet-beschikbaar', ['bewijsstuk' => $bewijsstuk], 503)
                ->header('Content-Security-Policy', self::CSP);
        }

        // Pas ná een geslaagde preview: een mislukte of geweigerde preview is
        // geen raadpleging, net als bij de download (§14).
        Raadpleging::create([
            'bewijsstuk_id' => $bewijsstuk->id,
            'gebruiker_id' => auth()->id(),
            'geraadpleegd_op' => now(),
        ]);

        return response()
            ->view('bewijs.preview', ['bewijsstuk' => $bewijsstuk, 'inhoud' => $inhoud])
            ->header('Content-Security-Policy', self::CSP)
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Het bestand zelf, in het browservenster. `nosniff` naast een expliciet
     * content-type is hier het punt: zonder dat mag de browser de inhoud zelf
     * beoordelen, en dan wordt een als PDF geüpload HTML-bestand een pagina op
     * ons eigen domein. De raadpleging staat hier om dezelfde reden als bij de
     * HTML-preview: pas als er echt iets te zien is.
     */
    private function inline(Bewijsstuk $bewijsstuk, string $type): StreamedResponse
    {
        Raadpleging::create([
            'bewijsstuk_id' => $bewijsstuk->id,
            'gebruiker_id' => auth()->id(),
            'geraadpleegd_op' => now(),
        ]);

        return Storage::disk(Bewijsstuk::DISK)->response(
            $bewijsstuk->opslaglocatie_referentie,
            $bewijsstuk->bestandsnaam,
            [
                'Content-Type' => $type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
