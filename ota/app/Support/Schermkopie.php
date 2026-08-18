<?php

namespace App\Support;

use App\Models\SchermkopieRegistratie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Een kopie van één scherm, als Word-document voor een auditor
 * (implementatie/12h).
 *
 * Een externe auditor zit vóór de schermen en vraagt bij wat hij ziet: "mag ik
 * hier een kopie van?". De scope is dus niet een vooraf samengesteld dossier
 * maar **dit scherm, zoals het er nu bij staat.**
 *
 * Twee regels dragen dit hele mechanisme:
 *
 * 1. **Het scherm declareert zijn kopie, de kopie rendert het scherm niet.** Geen
 *    HTML omzetten: dan sleep je navigatie en opmaak mee, en bepaalt de CSS wat
 *    er in een auditdocument staat. Kolommen en rijen komen uit code, net als
 *    bij {@see Meetbronnen}.
 * 2. **De rijen komen al opgehaald binnen** (§6). Een kopieknop die zijn eigen
 *    query doet, is een lek dat er precies uitziet als een feature:
 *    {@see Recordscope} beperkt op meerdere blokken wat een Medewerker ziet, en
 *    een tweede query zou dat stilzwijgend overslaan.
 */
final class Schermkopie
{
    /**
     * @param  list<string>  $kolommen
     * @param  list<array<int, string|int|float|null>>  $rijen  al opgehaald door het scherm (§6)
     * @param  array<string, string>  $filters  label => actieve waarde
     * @param  string  $eenheid  waar de rijen er één van zijn; alleen de omvangregel
     *                           gebruikt het, zodat een scherm dat geen register
     *                           toont geen "alle 5 regels" hoeft te zeggen
     * @param  Schermafbeelding|null  $afbeelding  optionele illustratie boven de tabel (§7a)
     * @param  Schermkopiebijlage|null  $bijlage  tweede tabel onder de hoofdtabel, voor een
     *                                            scherm met een tweede detailniveau
     */
    public function __construct(
        public readonly string $scherm,
        public readonly array $kolommen,
        public readonly array $rijen,
        public readonly int $totaalRijen,
        public readonly array $filters = [],
        public readonly ?string $toelichting = null,
        public readonly bool $metPersoonsgegevens = false,
        public readonly string $eenheid = 'regels',
        public readonly ?Schermafbeelding $afbeelding = null,
        public readonly ?Schermkopiebijlage $bijlage = null,
    ) {}

    /**
     * De regel waar §4 om draait: hoeveel van hoeveel, en waarop gefilterd.
     *
     * Zwijgen is geen optie. Een document met 36 rijen dat leest als het
     * volledige register is het gevaarlijkst denkbare artefact in een
     * auditdossier — een onvolledig overzicht dat zichzelf als compleet
     * presenteert.
     */
    public function omvangregel(): string
    {
        $aantal = count($this->rijen);

        if ($this->filters === [] && $aantal === $this->totaalRijen) {
            return 'Alle '.$this->totaalRijen.' '.$this->eenheid.'.';
        }

        $regel = $aantal.' van '.$this->totaalRijen.' '.$this->eenheid;

        if ($this->filters !== []) {
            $regel .= ' — filter: '.collect($this->filters)
                ->map(fn (string $waarde, string $label) => mb_strtolower($label).' '.$waarde)
                ->implode(', ');
        }

        return $regel.'.';
    }

    public function markdown(): string
    {
        $regels = [
            '# '.$this->scherm,
            '',
            '| | |',
            '|---|---|',
        ];

        // `app.organisatie` en niet `app.name`: dat laatste is de naam van het
        // product (EzISMS), niet die van de organisatie waarvoor het ISMS wordt
        // gevoerd. Is hij niet ingevuld, dan blijft de regel weg — "Organisatie:
        // —" in een auditdocument leest als een fout, en de naam van de software
        // erin zetten is erger dan hem weglaten.
        $organisatie = (string) config('app.organisatie');

        if ($organisatie !== '') {
            $regels[] = '| Organisatie | '.$this->veilig($organisatie).' |';
        }

        // Geen "Gemaakt op" meer: de printdatum staat sinds 13-08-2026 in de
        // voettekst van elke pagina, en dat is per definitie hetzelfde moment.
        // Twee namen voor één tijdstip in één document is iets waar een auditor
        // over struikelt. Het tijdstip zelf gaat niet verloren — het staat in de
        // bestandsnaam en in de vastlegging (`SchermkopieRegistratie`).
        $regels = [
            ...$regels,
            '| Gemaakt door | '.$this->veilig(auth()->user()?->naam ?? '—').' |',
            '| Omvang | '.$this->veilig($this->omvangregel()).' |',
            '',
        ];

        if ($this->toelichting !== null) {
            $regels[] = $this->toelichting;
            $regels[] = '';
        }

        // Op een eigen alinea, want pandoc maakt van een alleenstaande
        // afbeelding een figuur met de alt-tekst als onderschrift.
        if ($this->afbeelding !== null) {
            $regels[] = $this->afbeelding->markdown();
            $regels[] = '';
        }

        $regels = [...$regels, ...$this->tabel($this->kolommen, $this->rijen)];

        if ($this->bijlage !== null) {
            $regels[] = '';
            $regels[] = '## '.$this->bijlage->titel;
            $regels[] = '';

            if ($this->bijlage->toelichting !== null) {
                $regels[] = $this->bijlage->toelichting;
                $regels[] = '';
            }

            if ($this->bijlage->omvangregel !== null) {
                $regels[] = $this->veilig($this->bijlage->omvangregel);
                $regels[] = '';
            }

            $regels = [...$regels, ...$this->tabel($this->bijlage->kolommen, $this->bijlage->rijen)];
        }

        return implode("\n", $regels)."\n";
    }

    /**
     * Eén markdown-tabel. Een lege verzameling levert een rij streepjes op en
     * niet niets: een kop zonder rijen leest als een fout in het document.
     *
     * @param  list<string>  $kolommen
     * @param  list<array<int, string|int|float|null>>  $rijen
     * @return list<string>
     */
    private function tabel(array $kolommen, array $rijen): array
    {
        $uit = [
            '| '.implode(' | ', array_map($this->veilig(...), $kolommen)).' |',
            '|'.str_repeat('---|', count($kolommen)),
        ];

        foreach ($rijen as $rij) {
            $uit[] = '| '.collect($rij)
                ->map(fn ($cel) => $this->veilig($cel === null || $cel === '' ? '—' : (string) $cel))
                ->implode(' | ').' |';
        }

        if ($rijen === []) {
            $uit[] = '| '.implode(' | ', array_fill(0, count($kolommen), '—')).' |';
        }

        return $uit;
    }

    /**
     * @throws SchermkopieNietBeschikbaar wanneer pandoc ontbreekt of de conversie faalt
     */
    public function docx(?Pandoc $pandoc = null): string
    {
        $pandoc ??= app(Pandoc::class);

        if (! $pandoc->beschikbaar()) {
            throw new SchermkopieNietBeschikbaar(
                'Het Word-document kan niet worden gemaakt: pandoc ontbreekt op deze server.'
            );
        }

        try {
            // De volle voettekst: dit document is bewijs van déze organisatie,
            // dus de naam ervan hoort op elke pagina.
            return $pandoc->naarDocx($this->markdown(), Documentvoettekst::voorSchermkopie());
        } catch (Throwable $fout) {
            // Een knop die stil niets doet is erger dan geen knop (§7).
            throw new SchermkopieNietBeschikbaar(
                'Het Word-document kon niet worden gemaakt.', previous: $fout
            );
        }
    }

    public function bestandsnaam(): string
    {
        return Str::slug($this->scherm).'-'.Carbon::now()->format('Y-m-d-Hi').'.docx';
    }

    /**
     * Legt vast dát deze kopie is meegegeven (§9). Niet de inhoud: die wordt
     * nergens bewaard, en de vraag die beantwoordbaar moet blijven is wélk
     * scherm met welke filters de deur uit is gegaan.
     */
    public function legVast(): SchermkopieRegistratie
    {
        return SchermkopieRegistratie::create([
            'scherm' => $this->scherm,
            'filters' => $this->filters === [] ? null : $this->filters,
            'aantal_rijen' => count($this->rijen),
            'totaal_rijen' => $this->totaalRijen,
            'met_persoonsgegevens' => $this->metPersoonsgegevens,
            'gebruiker_id' => auth()->id(),
            'gemaakt_op' => Carbon::now(),
        ]);
    }

    /**
     * Een `|` in een celwaarde breekt de markdown-tabel: de rij krijgt er een
     * kolom bij en schuift scheef. Namen en omschrijvingen komen uit
     * gebruikersinvoer, dus dit gebeurt vanzelf een keer.
     */
    private function veilig(?string $waarde): string
    {
        return str_replace(["\r\n", "\n", '|'], [' ', ' ', '\\|'], (string) $waarde);
    }
}
