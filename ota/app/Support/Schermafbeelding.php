<?php

namespace App\Support;

/**
 * Een afbeelding in een schermkopie (implementatie/12h §7a).
 *
 * Er is één reden waarom dit een eigen klasse is: **pandoc draait met
 * `--sandbox`**, en dan komt hij niet bij het bestandssysteem. Een gewone
 * `![](plaatje.png)` levert dan een waarschuwing op stderr en een document
 * *zonder* plaatje — exit 0, geen fout, niets aan te zien. Precies de stille
 * mislukking die §7 verbiedt.
 *
 * Een `data:`-URI heeft geen bestandssysteem nodig en wordt wél ingesloten
 * (gemeten: het plaatje verschijnt als `word/media/rId9.png` in de docx). Dat
 * is hier dus geen optimalisatie maar de enige werkende weg, en daarom neemt
 * deze klasse de bytes aan en geen pad.
 *
 * De breedte staat in centimeters. Het document gaat naar Word en mogelijk naar
 * papier; de maat die telt is de maat op de pagina, niet het aantal pixels.
 */
final readonly class Schermafbeelding
{
    /**
     * @param  string  $png  de ruwe PNG-bytes
     * @param  string  $bijschrift  komt onder het plaatje te staan: pandoc maakt
     *                              van een alleenstaande afbeelding een figuur
     *                              met de alt-tekst als onderschrift
     */
    public function __construct(
        public string $png,
        public string $bijschrift,
        public float $breedteCm = 12.0,
    ) {}

    public function markdown(): string
    {
        // Blokhaken breken de afbeeldingssyntaxis; het bijschrift komt uit code,
        // maar dit is goedkoper dan erop vertrouwen.
        $bijschrift = str_replace(['[', ']'], '', $this->bijschrift);

        return '!['.$bijschrift.'](data:image/png;base64,'.base64_encode($this->png).')'
            .'{ width='.rtrim(rtrim(number_format($this->breedteCm, 2, '.', ''), '0'), '.').'cm }';
    }
}
