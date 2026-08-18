<?php

namespace App\Support;

/**
 * Een tweede tabel onder de hoofdtabel van een {@see Schermkopie}
 * (deelproducten/04c-bio-verplichtingen-zichtbaar.md §5).
 *
 * Ontstaan omdat de SoA in een BIO-installatie twee niveaus draagt: 93
 * beheersmaatregelen in de tabel en 118 verplichtingen eronder. Die tweede laag
 * paste niet in de kolommen van de eerste — "Van toepassing", "Restrisico" en
 * "Beleid" betekenen op dat niveau niets — en samenvatten tot één cel leverde
 * een auditdocument op waarin geen enkel nummer stond.
 *
 * Bewust generiek: dit is een bouwsteen van blok 12h en niet van één scherm. Een
 * volgend register met een tweede detailniveau krijgt hem gratis.
 *
 * De omvangregel is hier een gegeven en geen berekening. De hoofdtabel telt
 * rijen tegenover een register ("36 van 93 regels"); een bijlage telt vaak twee
 * grootheden tegelijk ("118 verplichtingen bij 54 van de 93 beheersmaatregelen")
 * en dat weet alleen het scherm zelf.
 */
final class Schermkopiebijlage
{
    /**
     * @param  list<string>  $kolommen
     * @param  list<array<int, string|int|float|null>>  $rijen  al opgehaald door het scherm (12h §6)
     */
    public function __construct(
        public readonly string $titel,
        public readonly array $kolommen,
        public readonly array $rijen,
        public readonly ?string $toelichting = null,
        public readonly ?string $omvangregel = null,
    ) {}
}
