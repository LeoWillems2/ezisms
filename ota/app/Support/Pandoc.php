<?php

namespace App\Support;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Dunne wikkel om de pandoc-binary, los van {@see Documentpreview} zodat de
 * conversie in tests te vervangen is zonder dat pandoc geïnstalleerd hoeft te
 * zijn.
 *
 * pandoc moet op de server staan (`apt install pandoc`, of een recente build)
 * en moet **>= 3.1.7** zijn: dat is de eerste versie met een RTF-lezer. Een
 * oudere versie kent `-f rtf` niet; de conversie faalt dan en de preview valt
 * terug op "niet beschikbaar" (zie {@see Documentpreview}).
 */
class Pandoc
{
    public function __construct(private readonly string $binary = 'pandoc') {}

    /** Draait de binary überhaupt? Goedkope check voor een nette foutmelding. */
    public function beschikbaar(): bool
    {
        $process = new Process([$this->binary, '--version']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Converteert een bestand naar een HTML-fragment. `$vanFormaat` is het
     * pandoc-bronformaat (rtf, docx, odt); het komt uit een vaste lijst
     * (`Bewijsstuk::PREVIEW_FORMATEN`), niet uit gebruikersinvoer. `--sandbox`
     * verbiedt pandoc toegang tot het bestandssysteem tijdens de conversie,
     * zodat een geprepareerd document geen lokale bestanden kan inlezen. Geen
     * `--standalone`: we willen een fragment, geen volledige pagina met eigen
     * <head>.
     *
     * @throws ProcessFailedException
     */
    public function naarHtml(string $absoluutPad, string $vanFormaat): string
    {
        $process = new Process([
            $this->binary, '--sandbox', '--from='.$vanFormaat, '--to=html', $absoluutPad,
        ]);
        $process->setTimeout(20);
        $process->mustRun();

        return $process->getOutput();
    }

    /**
     * Zet markdown om in de binaire inhoud van een `.docx` (implementatie/12h §7).
     *
     * Via stdin en stdout, dus zonder tijdelijke bestanden: er staat nergens
     * onderweg een leesbare kopie van een register op schijf, en er is niets op
     * te ruimen als de conversie faalt. `--sandbox` blijft staan.
     *
     * Met een voettekst komt er wél een bestand aan te pas, maar níet met de
     * inhoud erin: zie {@see referentiedocumentMet()}.
     *
     * @throws ProcessFailedException
     */
    public function naarDocx(string $markdown, ?Documentvoettekst $voettekst = null): string
    {
        if ($voettekst === null) {
            return $this->converteer($markdown);
        }

        $referentie = $this->referentiedocumentMet($voettekst);

        try {
            return $this->converteer($markdown, $referentie);
        } finally {
            @unlink($referentie);
        }
    }

    private function converteer(string $markdown, ?string $referentie = null): string
    {
        $opdracht = [$this->binary, '--sandbox', '--from=markdown', '--to=docx', '--output=-'];

        if ($referentie !== null) {
            $opdracht[] = '--reference-doc='.$referentie;
        }

        $process = new Process($opdracht);
        $process->setInput($markdown);
        $process->setTimeout(20);
        $process->mustRun();

        return $process->getOutput();
    }

    /**
     * Pandocs eigen sjabloon, met de voettekst erin gehangen, op een tijdelijk
     * pad.
     *
     * Markdown kent geen paginavoet; pandoc haalt die uitsluitend uit het
     * document achter `--reference-doc`, en dat moet een pad zijn. Vandaar dat de
     * regel hierboven — niets op schijf — hier één uitzondering krijgt, en welke
     * uitzondering dat is doet ertoe: op schijf komt het lege sjabloon plus de
     * voettekst (organisatienaam, norm, versie, datum). De rijen van het register
     * gaan nog altijd via stdin en stdout en raken de schijf niet.
     *
     * Het sjabloon komt uit de geïnstalleerde pandoc zelf en niet uit de repo:
     * dan kan het niet uit de pas lopen met de versie die de conversie doet, en
     * blijft er geen binair bestand in een repo staan die leesbaar moet blijven.
     *
     * @return string het pad; de aanroeper ruimt het op
     *
     * @throws ProcessFailedException
     * @throws \RuntimeException
     */
    private function referentiedocumentMet(Documentvoettekst $voettekst): string
    {
        $sjabloon = new Process([$this->binary, '--print-default-data-file', 'reference.docx']);
        $sjabloon->setTimeout(20);
        $sjabloon->mustRun();

        $pad = tempnam(sys_get_temp_dir(), 'ezisms-voettekst-');

        if ($pad === false) {
            throw new \RuntimeException('Geen tijdelijk bestand te maken voor het referentiedocument.');
        }

        try {
            file_put_contents($pad, $sjabloon->getOutput());
            $voettekst->inDocx($pad);
        } catch (\Throwable $fout) {
            @unlink($pad);

            throw $fout;
        }

        return $pad;
    }
}
