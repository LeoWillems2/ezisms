<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use RuntimeException;
use ZipArchive;

/**
 * De band onderaan elke pagina van een Word-document: waar het vandaan komt, uit
 * welke installatie, en het hoeveelste blad van hoeveel je in handen hebt.
 *
 * **Waarom dit een aparte klasse is en geen regel markdown.** Alle Word-documenten
 * hier ontstaan via {@see Pandoc}: markdown erin, `.docx` eruit. Markdown kent
 * geen paginavoet — het weet niet eens wat een pagina is. Pandoc haalt
 * voetteksten uitsluitend uit het document dat je met `--reference-doc` meegeeft.
 * Deze klasse maakt daarom het onderdeel `word/footer1.xml` dat daarin gehangen
 * wordt, plus de drie verwijzingen die een docx nodig heeft om het te vinden.
 *
 * **Het paginatotaal rekenen wij niet uit.** Wij renderen nooit pagina's, dus
 * "3 van 12" is aan onze kant onkenbaar. Het worden Word-veldcodes (`PAGE` en
 * `NUMPAGES`) die de lezer zelf invult bij het openen of afdrukken. Gevolg: in
 * een viewer die velden niet evalueert blijft het getal leeg. Dat is voor docx
 * de enige route; een vast getal zou op elke pagina behalve één liegen.
 *
 * **De omgeving valt de veilige kant op.** Alleen exact `production` levert
 * "Productieversie"; `local`, `demo`, `testing` en alles wat er ooit bijkomt
 * heten "Ontwikkelversie". De gevaarlijke fout is een demokopie die in een
 * auditdossier beweert uit de productieomgeving te komen — niet andersom.
 *
 * De indeling is drie kolommen: herkomst links, product in het midden, datum en
 * paginanummer rechts. Een tabel en geen tabstops, want pandoc laat het
 * paginaformaat aan Word over: zonder `w:pgSz` is de tekstbreedte onbekend en
 * staat een rechtertabstop op een gokgetal. Kolombreedtes in procenten kennen
 * dat probleem niet.
 */
final class Documentvoettekst
{
    /** Halve punten, dus 8pt. Groter en de voet gaat met de tekst concurreren. */
    private const GROOTTE = '16';

    /** Grijs, zodat de band niet als inhoud leest. */
    private const KLEUR = '595959';

    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const TYPE_VOETTEKST = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer';

    private const INHOUDSTYPE_VOETTEKST =
        'application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml';

    /**
     * @param  string  $links  herkomst: organisatie en norm
     * @param  string  $midden  product, versie en omgeving
     * @param  string  $rechts  de printdatum; het paginanummer komt er in de XML
     *                          achteraan, want dat zijn veldcodes en geen tekst
     */
    public function __construct(
        public readonly string $links,
        public readonly string $midden,
        public readonly string $rechts,
    ) {}

    /**
     * De volle voettekst, voor een schermkopie voor de auditor. Dat document is
     * bewijsmateriaal van déze organisatie, dus de organisatienaam hoort erbij.
     */
    public static function voorSchermkopie(): self
    {
        $organisatie = trim((string) config('app.organisatie'));
        $norm = Normprofiel::label('naam');

        return new self(
            // Leeg blijft leeg: `Schermkopie` laat de organisatieregel in de kop
            // ook weg in plaats van "—" te tonen, en de norm alleen is een
            // geldige herkomstvermelding.
            links: $organisatie === '' ? $norm : $organisatie.' · '.$norm,
            midden: self::product(),
            rechts: self::printdatum(),
        );
    }

    /**
     * Dezelfde band, maar zonder de organisatienaam.
     *
     * Een kennisartikel is meegeleverde documentatie uit de repo en geen bewijs
     * van deze organisatie — dat staat ook zo in de kop van de controller
     * `DownloadKennisartikel`. Er de organisatienaam onder zetten zou suggereren
     * dat zij het geschreven heeft, en dat is precies de verwarring die je in een
     * auditdossier niet wil.
     */
    public static function voorKennisartikel(): self
    {
        return new self(
            links: Normprofiel::label('naam'),
            midden: self::product(),
            rechts: self::printdatum(),
        );
    }

    /** `EzISMS V2.5.0 · Ontwikkelversie`, of zonder nummer als er geen versie is. */
    private static function product(): string
    {
        $product = trim((string) config('app.name', 'EzISMS'));
        $versie = trim((string) config('app.versie'));

        // Leeg versienummer blijft weg, net als in de zijbalk: beter niets tonen
        // dan een nummer dat niet klopt (zie config/app.php).
        if ($versie !== '') {
            $product .= ' '.$versie;
        }

        return $product.' · '.self::omgeving();
    }

    /**
     * `config('app.env')` en niet `app()->environment()`: dat laatste leest de
     * container-binding `env`, die bij het opstarten één keer wordt gezet en
     * daarna niet meer meebeweegt met de configuratie. Een test die de omgeving
     * omzet zou dan stilzwijgend de oude waarde blijven toetsen.
     */
    private static function omgeving(): string
    {
        return config('app.env') === 'production' ? 'Productieversie' : 'Ontwikkelversie';
    }

    private static function printdatum(): string
    {
        return 'Printdatum '.Carbon::now()->lokaal()->format('d/m/Y');
    }

    /**
     * Hangt deze voettekst in een docx-bestand op schijf.
     *
     * Bedoeld voor het referentiedocument van pandoc, niet voor een afgerond
     * document: {@see Pandoc::naarDocx()} zet pandocs eigen sjabloon apart, hangt
     * de voet erin en laat pandoc de rest doen. Zo staat er nooit een register op
     * schijf — alleen een sjabloon met een organisatienaam erin.
     *
     * Vier ingrepen, want minder is een docx die Word weigert te openen: het
     * onderdeel zelf, het inhoudstype ervan, de relatie ernaartoe, en de
     * verwijzing vanuit de sectie-eigenschappen.
     *
     * @throws RuntimeException wanneer het bestand geen leesbare docx is
     */
    public function inDocx(string $pad): void
    {
        $zip = new ZipArchive;

        if ($zip->open($pad) !== true) {
            throw new RuntimeException('Het referentiedocument voor pandoc is geen leesbare docx.');
        }

        try {
            $relaties = $zip->getFromName('word/_rels/document.xml.rels');
            $inhoudstypen = $zip->getFromName('[Content_Types].xml');
            $document = $zip->getFromName('word/document.xml');

            if ($relaties === false || $inhoudstypen === false || $document === false) {
                throw new RuntimeException(
                    'Het referentiedocument mist onderdelen die er in elke docx horen te zitten.'
                );
            }

            $relatieId = self::vrijeRelatieId($relaties);

            $zip->addFromString('word/footer1.xml', $this->xml());
            $zip->addFromString('word/_rels/document.xml.rels', self::metRelatie($relaties, $relatieId));
            $zip->addFromString('[Content_Types].xml', self::metInhoudstype($inhoudstypen));
            $zip->addFromString('word/document.xml', self::metVerwijzing($document, $relatieId));
        } finally {
            $zip->close();
        }
    }

    /** Het onderdeel `word/footer1.xml`. */
    public function xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:ftr xmlns:w="'.self::NS_W.'" xmlns:r="'.self::NS_R.'">'
            .'<w:tbl>'
            .'<w:tblPr>'
            .'<w:tblW w:w="5000" w:type="pct"/>'
            .self::randenWeg()
            // Zonder deze nullen schuift Word elke cel een halve centimeter naar
            // binnen en staat de linkerkolom niet meer onder de kantlijn.
            .'<w:tblCellMar>'
            .'<w:top w:w="0" w:type="dxa"/><w:left w:w="0" w:type="dxa"/>'
            .'<w:bottom w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/>'
            .'</w:tblCellMar>'
            .'<w:tblLook w:val="0000"/>'
            .'</w:tblPr>'
            .'<w:tblGrid><w:gridCol w:w="3000"/><w:gridCol w:w="3000"/><w:gridCol w:w="3000"/></w:tblGrid>'
            .'<w:tr>'
            .$this->cel('1667', 'left', $this->tekst($this->links))
            .$this->cel('1666', 'center', $this->tekst($this->midden))
            .$this->cel('1667', 'right', $this->tekst($this->rechts.' · ')
                .$this->veld('PAGE')
                .$this->tekst(' van ')
                .$this->veld('NUMPAGES'))
            .'</w:tr>'
            .'</w:tbl>'
            // Een tabel mag niet het laatste zijn in een voettekst; er moet een
            // alinea achter. Zo klein mogelijk, anders groeit de voet.
            .'<w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:rPr><w:sz w:val="2"/></w:rPr></w:pPr></w:p>'
            .'</w:ftr>';
    }

    private function cel(string $breedte, string $uitlijning, string $inhoud): string
    {
        return '<w:tc>'
            .'<w:tcPr><w:tcW w:w="'.$breedte.'" w:type="pct"/></w:tcPr>'
            .'<w:p><w:pPr>'
            .'<w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/>'
            .'<w:jc w:val="'.$uitlijning.'"/>'
            .$this->opmaak()
            .'</w:pPr>'
            .$inhoud
            .'</w:p>'
            .'</w:tc>';
    }

    private function tekst(string $waarde): string
    {
        return '<w:r>'.$this->opmaak().'<w:t xml:space="preserve">'
            .htmlspecialchars($waarde, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</w:t></w:r>';
    }

    /**
     * Een veldcode zonder gecachet antwoord: de lege run draagt alleen de opmaak,
     * Word vult het getal in. Er bewust géén "1" in zetten — dat getal zou op elke
     * pagina behalve de eerste verkeerd zijn zolang de velden niet zijn ververst.
     */
    private function veld(string $code): string
    {
        return '<w:fldSimple w:instr=" '.$code.' "><w:r>'.$this->opmaak().'</w:r></w:fldSimple>';
    }

    private function opmaak(): string
    {
        return '<w:rPr>'
            .'<w:color w:val="'.self::KLEUR.'"/>'
            .'<w:sz w:val="'.self::GROOTTE.'"/><w:szCs w:val="'.self::GROOTTE.'"/>'
            .'</w:rPr>';
    }

    private static function randenWeg(): string
    {
        $randen = '';

        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $zijde) {
            $randen .= '<w:'.$zijde.' w:val="none" w:sz="0" w:space="0" w:color="auto"/>';
        }

        return '<w:tblBorders>'.$randen.'</w:tblBorders>';
    }

    /**
     * Een relatie-id dat nog niet bezet is. Pandoc gebruikt `rId1` tot en met
     * `rId8`, maar dat is geen belofte: bij een nieuwe pandoc kan het er meer of
     * minder zijn, en een dubbel id laat Word het document weigeren.
     */
    private static function vrijeRelatieId(string $relaties): string
    {
        preg_match_all('/Id="rId(\d+)"/', $relaties, $treffers);

        $hoogste = $treffers[1] === [] ? 0 : max(array_map('intval', $treffers[1]));

        return 'rId'.($hoogste + 1);
    }

    private static function metRelatie(string $relaties, string $relatieId): string
    {
        return str_replace(
            '</Relationships>',
            '<Relationship Id="'.$relatieId.'" Type="'.self::TYPE_VOETTEKST.'" Target="footer1.xml"/>'
            .'</Relationships>',
            $relaties
        );
    }

    private static function metInhoudstype(string $inhoudstypen): string
    {
        if (str_contains($inhoudstypen, '/word/footer1.xml')) {
            return $inhoudstypen;
        }

        return str_replace(
            '</Types>',
            '<Override PartName="/word/footer1.xml" ContentType="'.self::INHOUDSTYPE_VOETTEKST.'"/>'
            .'</Types>',
            $inhoudstypen
        );
    }

    /**
     * De verwijzing hoort in `w:sectPr`, en wel als eerste kind: het schema legt
     * de volgorde vast en `w:footerReference` gaat vóór `w:footnotePr`, dat
     * pandoc er wél in zet.
     */
    private static function metVerwijzing(string $document, string $relatieId): string
    {
        $verwijzing = '<w:footerReference w:type="default" r:id="'.$relatieId.'"/>';

        // Zelfsluitend eerst, anders vangt het tweede patroon hem ook en levert
        // `<w:sectPr/><w:footerReference/>` op — buiten de sectie dus.
        $uitgevouwen = preg_replace(
            '/<w:sectPr\b([^>]*?)\/>/',
            '<w:sectPr$1>'.$verwijzing.'</w:sectPr>',
            $document,
            1,
            $aantal
        );

        if ($aantal === 1 && $uitgevouwen !== null) {
            return $uitgevouwen;
        }

        $met = preg_replace('/<w:sectPr\b[^>]*>/', '$0'.$verwijzing, $document, 1, $aantal);

        if ($aantal === 1 && $met !== null) {
            return $met;
        }

        // Geen sectie-eigenschappen in het sjabloon: er zelf een neerzetten. Dat
        // hoort niet te gebeuren, maar een document zonder voettekst is beter dan
        // een uitzondering op een downloadknop.
        return str_replace('</w:body>', '<w:sectPr>'.$verwijzing.'</w:sectPr></w:body>', $document);
    }
}
