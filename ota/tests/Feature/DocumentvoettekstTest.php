<?php

namespace Tests\Feature;

use App\Support\Documentvoettekst;
use App\Support\Pandoc;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

/**
 * De band onderaan elke pagina van een Word-document.
 *
 * Wat hier fout kan gaan valt in twee soorten uiteen, en die worden apart
 * getoetst. De ene is een verkeerde bewering: een demokopie die zegt uit
 * productie te komen, of een kennisartikel uit de repo met de naam van de
 * organisatie eronder. Dat is een document dat in een auditdossier iets beweert
 * wat niet waar is. De andere is kapotte XML — die merk je niet in de tests maar
 * pas als Word het bestand weigert te openen, en dan sta je voor de auditor.
 *
 * Geen test kan hier het gerenderde resultaat zien: wij maken de veldcodes, Word
 * rekent de pagina's. Wat wél te toetsen valt is dat de codes erin staan en dat
 * alle vier de onderdelen die een docx nodig heeft aan elkaar geknoopt zijn.
 */
class DocumentvoettekstTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rechtstreeks in de configuratie: het profiel wordt normaal uit de
        // tabel `normprofiel` gelezen, en daar heeft deze test geen database
        // voor nodig.
        config(['norm.actief' => 'iso27001']);
        config(['app.organisatie' => 'Fruit BV', 'app.name' => 'EzISMS', 'app.versie' => 'V2.5.0']);
    }

    public function test_de_linkerkolom_noemt_de_organisatie_en_de_norm(): void
    {
        $this->assertSame('Fruit BV · ISO/IEC 27001', Documentvoettekst::voorSchermkopie()->links);
    }

    public function test_zonder_organisatienaam_blijft_alleen_de_norm_over(): void
    {
        config(['app.organisatie' => '']);

        // Geen "— · ISO/IEC 27001": een streepje in een auditdocument leest als
        // een fout, terwijl de norm alleen een geldige herkomstvermelding is.
        $this->assertSame('ISO/IEC 27001', Documentvoettekst::voorSchermkopie()->links);
    }

    public function test_het_kennisartikel_draagt_de_organisatienaam_niet(): void
    {
        $voettekst = Documentvoettekst::voorKennisartikel();

        $this->assertStringNotContainsString('Fruit BV', $voettekst->links);
        $this->assertSame('ISO/IEC 27001', $voettekst->links);
    }

    public function test_de_middenkolom_noemt_product_versie_en_omgeving(): void
    {
        config(['app.env' => 'production']);

        $this->assertSame('EzISMS V2.5.0 · Productieversie', Documentvoettekst::voorSchermkopie()->midden);
    }

    public function test_een_leeg_versienummer_blijft_weg(): void
    {
        config(['app.versie' => '', 'app.env' => 'production']);

        // Zoals in de zijbalk: beter niets tonen dan een nummer dat niet klopt.
        $this->assertSame('EzISMS · Productieversie', Documentvoettekst::voorSchermkopie()->midden);
    }

    /**
     * De asymmetrie is het punt: alleen exact `production` mag zich zo noemen.
     * Een demokopie die in een dossier beweert uit productie te komen is de
     * gevaarlijke fout; een productiedocument dat zichzelf te bescheiden
     * aankondigt kost niemand iets.
     */
    public function test_alleen_production_heet_productieversie(): void
    {
        foreach (['local', 'demo', 'testing', 'staging', 'Production', ''] as $omgeving) {
            config(['app.env' => $omgeving]);

            $this->assertSame(
                'EzISMS V2.5.0 · Ontwikkelversie',
                Documentvoettekst::voorSchermkopie()->midden,
                "APP_ENV={$omgeving} hoort geen productieversie te heten."
            );
        }

        config(['app.env' => 'production']);

        $this->assertStringContainsString('Productieversie', Documentvoettekst::voorSchermkopie()->midden);
    }

    public function test_de_printdatum_staat_er_als_dag_maand_jaar(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 14:20:00'));

        $this->assertSame('Printdatum 13/08/2026', Documentvoettekst::voorSchermkopie()->rechts);
    }

    public function test_de_xml_is_welgevormd(): void
    {
        $vorige = libxml_use_internal_errors(true);

        $document = simplexml_load_string(Documentvoettekst::voorSchermkopie()->xml());

        $fouten = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($vorige);

        $this->assertNotFalse($document, 'De voettekst-XML is niet te lezen: '
            .implode(' ', array_map(fn ($fout) => trim($fout->message), $fouten)));
    }

    public function test_het_paginanummer_bestaat_uit_veldcodes(): void
    {
        $xml = Documentvoettekst::voorSchermkopie()->xml();

        // Wij kennen het totaal niet; Word rekent het uit bij het openen.
        $this->assertStringContainsString('w:instr=" PAGE "', $xml);
        $this->assertStringContainsString('w:instr=" NUMPAGES "', $xml);
        $this->assertStringContainsString('van', $xml);

        // Geen gecachet antwoord ín het veld: dat getal zou op elke pagina
        // behalve één verkeerd zijn zolang de velden niet zijn ververst. De run
        // die er wél in staat draagt alleen de opmaak, zonder tekstelement.
        foreach (['PAGE', 'NUMPAGES'] as $code) {
            $this->assertMatchesRegularExpression(
                '/<w:fldSimple w:instr=" '.$code.' ">(.*?)<\/w:fldSimple>/',
                $xml
            );

            preg_match('/<w:fldSimple w:instr=" '.$code.' ">(.*?)<\/w:fldSimple>/', $xml, $treffer);

            $this->assertStringNotContainsString('<w:t', $treffer[1],
                "Het veld {$code} draagt een vast getal met zich mee.");
        }
    }

    public function test_een_ampersand_in_de_organisatienaam_breekt_de_xml_niet(): void
    {
        config(['app.organisatie' => 'Jansen & Zonen <BV>']);

        $xml = Documentvoettekst::voorSchermkopie()->xml();

        $this->assertStringContainsString('Jansen &amp; Zonen &lt;BV&gt;', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    /**
     * Vier ingrepen, en alle vier zijn ze nodig: mist er één, dan weigert Word
     * het bestand of laat het de voet gewoon weg. Dat is niet aan de PHP-kant te
     * zien, dus wordt het hier op een echte zip nagelopen.
     */
    public function test_alle_vier_de_onderdelen_worden_aan_elkaar_geknoopt(): void
    {
        $pad = $this->minimaleDocx();

        try {
            Documentvoettekst::voorSchermkopie()->inDocx($pad);

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($pad) === true);

            $voettekst = $zip->getFromName('word/footer1.xml');
            $relaties = $zip->getFromName('word/_rels/document.xml.rels');
            $typen = $zip->getFromName('[Content_Types].xml');
            $document = $zip->getFromName('word/document.xml');
            $zip->close();

            $this->assertIsString($voettekst);
            $this->assertStringContainsString('Fruit BV · ISO/IEC 27001', $voettekst);

            // rId1 en rId2 zijn bezet in het nepdocument, dus rId3 is aan de beurt.
            $this->assertStringContainsString('Id="rId3"', $relaties);
            $this->assertStringContainsString('Target="footer1.xml"', $relaties);
            $this->assertStringContainsString('/word/footer1.xml', $typen);

            // Vóór w:footnotePr: het schema legt de volgorde binnen w:sectPr vast.
            $this->assertStringContainsString(
                '<w:sectPr><w:footerReference w:type="default" r:id="rId3"/><w:footnotePr/>',
                $document
            );
        } finally {
            @unlink($pad);
        }
    }

    public function test_een_zelfsluitende_sectie_wordt_opengevouwen(): void
    {
        $pad = $this->minimaleDocx('<w:sectPr w:rsidR="00A1"/>');

        try {
            Documentvoettekst::voorSchermkopie()->inDocx($pad);

            $zip = new ZipArchive;
            $zip->open($pad);
            $document = $zip->getFromName('word/document.xml');
            $zip->close();

            // De attributen blijven staan en de verwijzing komt erbinnen te staan,
            // niet erachter.
            $this->assertStringContainsString(
                '<w:sectPr w:rsidR="00A1"><w:footerReference w:type="default" r:id="rId3"/></w:sectPr>',
                $document
            );
        } finally {
            @unlink($pad);
        }
    }

    public function test_zonder_sectie_eigenschappen_worden_die_alsnog_gemaakt(): void
    {
        $pad = $this->minimaleDocx('');

        try {
            Documentvoettekst::voorSchermkopie()->inDocx($pad);

            $zip = new ZipArchive;
            $zip->open($pad);
            $document = $zip->getFromName('word/document.xml');
            $zip->close();

            // Een document zonder voettekst is beter dan een downloadknop die
            // een uitzondering gooit.
            $this->assertStringContainsString('<w:sectPr><w:footerReference', $document);
            $this->assertStringContainsString('</w:sectPr></w:body>', $document);
        } finally {
            @unlink($pad);
        }
    }

    public function test_een_onleesbaar_bestand_levert_een_uitzondering_op(): void
    {
        $pad = tempnam(sys_get_temp_dir(), 'ezisms-toets-');
        file_put_contents($pad, 'dit is geen zip');

        try {
            $this->expectException(\RuntimeException::class);

            Documentvoettekst::voorSchermkopie()->inDocx($pad);
        } finally {
            @unlink($pad);
        }
    }

    /**
     * Eén keer de echte binary, net als bij de schermkopie en de kennisbank: dit
     * is de enige toets die bewijst dat pandoc de voettekst uit het
     * referentiedocument ook daadwerkelijk meeneemt naar het uitgeleverde
     * document. Zonder deze stap zou alles hierboven kunnen kloppen terwijl de
     * lezer een document zonder voet krijgt.
     */
    public function test_pandoc_neemt_de_voettekst_mee_in_het_document(): void
    {
        if (! (new Pandoc)->beschikbaar()) {
            $this->markTestSkipped('pandoc staat niet op deze machine.');
        }

        Carbon::setTestNow(Carbon::parse('2026-08-13 14:20:00'));
        config(['app.env' => 'production']);

        $docx = (new Pandoc)->naarDocx(
            "# Proef\n\nEen alinea.\n",
            Documentvoettekst::voorSchermkopie()
        );

        $pad = tempnam(sys_get_temp_dir(), 'ezisms-toets-');
        file_put_contents($pad, $docx);

        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($pad) === true, 'Pandoc leverde geen leesbare docx.');

            $voettekst = $zip->getFromName('word/footer1.xml');
            $document = $zip->getFromName('word/document.xml');
            $zip->close();

            $this->assertIsString($voettekst, 'De voettekst haalde het uitgeleverde document niet.');
            $this->assertStringContainsString('Fruit BV · ISO/IEC 27001', $voettekst);
            $this->assertStringContainsString('EzISMS V2.5.0 · Productieversie', $voettekst);
            $this->assertStringContainsString('Printdatum 13/08/2026', $voettekst);
            $this->assertStringContainsString('w:instr=" NUMPAGES "', $voettekst);
            $this->assertStringContainsString('footerReference', $document);
        } finally {
            @unlink($pad);
        }
    }

    /** Het tijdelijke referentiedocument mag de conversie niet overleven. */
    public function test_er_blijft_geen_referentiedocument_achter(): void
    {
        if (! (new Pandoc)->beschikbaar()) {
            $this->markTestSkipped('pandoc staat niet op deze machine.');
        }

        $voor = glob(sys_get_temp_dir().'/ezisms-voettekst-*') ?: [];

        (new Pandoc)->naarDocx("# Proef\n", Documentvoettekst::voorSchermkopie());

        $na = glob(sys_get_temp_dir().'/ezisms-voettekst-*') ?: [];

        $this->assertSame($voor, $na);
    }

    /**
     * Het kleinste bestand dat als docx doorgaat voor wat {@see Documentvoettekst}
     * ermee doet. Met de hand gebouwd en niet uit pandoc getrokken: dan toetst
     * deze test ook op een machine zonder pandoc, en is te zien wat de code
     * verwacht aan te treffen.
     */
    private function minimaleDocx(string $sectie = '<w:sectPr><w:footnotePr/></w:sectPr>'): string
    {
        $pad = tempnam(sys_get_temp_dir(), 'ezisms-toets-');

        $zip = new ZipArchive;
        $zip->open($pad, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8"?><Types><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip->addFromString('word/_rels/document.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?><Relationships>'
            .'<Relationship Id="rId1" Target="styles.xml"/><Relationship Id="rId2" Target="settings.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/document.xml',
            '<?xml version="1.0" encoding="UTF-8"?><w:document><w:body><w:p/>'.$sectie.'</w:body></w:document>');
        $zip->close();

        return $pad;
    }
}
