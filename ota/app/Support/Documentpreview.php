<?php

namespace App\Support;

use App\Models\Bewijsstuk;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Tekstverwerkerbestand -> gesaneerde HTML-preview (blok 5/6). De conversie
 * loopt via {@see Pandoc}; het resultaat wordt gesaneerd tot een vaste allowlist
 * van tags en attributen. PDF's gaan hier niet doorheen: die serveert de
 * previewcontroller inline, zonder conversie.
 *
 * De sanitisatie is één van drie lagen. De controller serveert de preview met
 * een strikte Content-Security-Policy (geen scripts, geen externe bronnen), en
 * pandoc-uitvoer bevat sowieso geen scripts. Deze allowlist is de laag die ook
 * niet-script-onzin (externe <img>, formulieren, <style>) weghaalt.
 */
class Documentpreview
{
    /** Tags die een leesbare preview nodig heeft; de rest wordt verwijderd. */
    private const TOEGESTANE_TAGS = [
        'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'span', 'div',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
        'a',
    ];

    /** Per tag de attributen die mogen blijven; al het andere wordt gestript. */
    private const TOEGESTANE_ATTRIBUTEN = [
        'a' => ['href'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    public function __construct(private readonly Pandoc $pandoc) {}

    public function isPreviewbaar(Bewijsstuk $bewijsstuk): bool
    {
        return $bewijsstuk->isPreviewbaar();
    }

    /**
     * Gesaneerde HTML van een bewijsstuk. Gecachet op de bestandshash:
     * verandert het bestand, dan verandert de hash en vervalt de cache vanzelf,
     * zonder dat we hem expliciet hoeven te legen.
     *
     * @throws PreviewNietBeschikbaar
     */
    public function html(Bewijsstuk $bewijsstuk): string
    {
        $formaat = $bewijsstuk->previewFormaat();

        if ($formaat === null) {
            throw new PreviewNietBeschikbaar('Dit bestandstype heeft geen preview.');
        }

        try {
            // Alleen een geslaagde conversie belandt in de cache: gooit de
            // closure, dan slaat rememberForever niets op.
            return Cache::rememberForever("bewijs-preview:{$bewijsstuk->bestandshash}", function () use ($bewijsstuk, $formaat) {
                $pad = Storage::disk(Bewijsstuk::DISK)->path($bewijsstuk->opslaglocatie_referentie);

                return $this->saneer($this->pandoc->naarHtml($pad, $formaat));
            });
        } catch (Throwable $e) {
            throw new PreviewNietBeschikbaar('Conversie naar een HTML-preview mislukte.', previous: $e);
        }
    }

    /**
     * Behoudt alleen tags en attributen uit de allowlist. Onbekende elementen
     * (script, style, iframe, object, form, …) verdwijnen volledig; commentaar
     * en processing instructions ook.
     */
    private function saneer(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument;
        $vorige = libxml_use_internal_errors(true);
        // Expliciete UTF-8 zodat DOMDocument het niet als Latin-1 leest; LIBXML_NONET
        // blokkeert het ophalen van externe entiteiten/DTD's.
        $dom->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__preview_wortel">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($vorige);

        $wortel = $dom->getElementById('__preview_wortel');

        if ($wortel === null) {
            return '';
        }

        $this->schoonNode($wortel);

        $uit = '';
        foreach ($wortel->childNodes as $kind) {
            $uit .= $dom->saveHTML($kind);
        }

        return trim($uit);
    }

    private function schoonNode(DOMNode $node): void
    {
        // Snapshot: we muteren de kinderen tijdens het itereren.
        foreach (iterator_to_array($node->childNodes) as $kind) {
            if ($kind instanceof DOMElement) {
                if (! in_array(strtolower($kind->tagName), self::TOEGESTANE_TAGS, true)) {
                    $kind->parentNode->removeChild($kind);

                    continue;
                }

                $this->stripAttributen($kind);
                $this->schoonNode($kind);
            } elseif (! ($kind instanceof \DOMText)) {
                // Commentaar, CDATA, processing instructions: weg. Tekst blijft.
                $kind->parentNode->removeChild($kind);
            }
        }
    }

    private function stripAttributen(DOMElement $element): void
    {
        $toegestaan = self::TOEGESTANE_ATTRIBUTEN[strtolower($element->tagName)] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribuut) {
            $naam = strtolower($attribuut->name);

            if (! in_array($naam, $toegestaan, true) || ! $this->veiligeWaarde($naam, $attribuut->value)) {
                $element->removeAttribute($attribuut->name);
            }
        }
    }

    private function veiligeWaarde(string $naam, string $waarde): bool
    {
        // Alleen href kan een gevaarlijk schema dragen (javascript:, data:).
        if ($naam === 'href') {
            return (bool) preg_match('#^\s*(https?:|mailto:|#)#i', $waarde);
        }

        return true;
    }
}
