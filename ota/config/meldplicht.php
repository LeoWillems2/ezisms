<?php

/**
 * De wettelijke meldtermijnen bij een extern meldingsplichtig incident
 * (implementatie/08b-externe-meldplicht.md §5).
 *
 * Dit bestand is de enige plek waar de termijnen staan. Geen `match` op
 * grondslag in de code: termijnen veranderen bij wet, en dan wil je één bestand
 * aanpassen en in de diff zien wat er verandert.
 *
 * De waarden zijn een **voorstel**. De gebruiker mag `uiterlijk_op` per melding
 * overschrijven, want er zijn regimes met andere termijnen — entiteiten die ook
 * onder DORA of de netcode cyberbeveiliging elektriciteit vallen, melden binnen
 * vier uur. Een enum-waarde bouwen voor elk regime is speculatief; overschrijven
 * volstaat.
 *
 * `anker` zegt vanaf welk moment de termijn loopt, en dat verschilt per fase:
 *
 *  - `kennisname`  — vanaf `incidenten.kennisname_op`. De wettelijke start:
 *                    Cbw art. 26/27 "nadat zij kennis heeft gekregen van het
 *                    significante incident", AVG art. 33 lid 1 "nadat hij er
 *                    kennis van heeft genomen". Níet `gemeld_op`.
 *  - `melding`     — vanaf het moment waarop de melding uit Cbw art. 27 lid 1 is
 *                    gedaan (art. 29 lid 1).
 *  - `afhandeling` — pas te bepalen als het incident is afgehandeld
 *                    (Cbw art. 29 lid 2). Levert `uiterlijk_op = null` zolang het
 *                    incident loopt: "verplicht, nog geen datum".
 *  - `geen`        — er is geen termijn. AVG art. 34 zegt "onverwijld" en noemt
 *                    geen getal.
 *
 * Geverifieerd op 2026-08-04 tegen Verordening (EU) 2016/679 en de
 * Cyberbeveiligingswet (BWBR0052872, tekst geldend vanaf 15 augustus 2026).
 */
return [

    /*
     * Valt deze organisatie onder de Cyberbeveiligingswet?
     *
     * Dat hangt af van sector en omvang en is een juridisch oordeel dat de
     * organisatie één keer maakt — niet iets om per incident te vragen. Staat
     * dit uit, dan stelt het incidentscherm de Cbw-vraag helemaal niet en blijft
     * er voor een incident zonder persoonsgegevens niets in te vullen.
     *
     * **De standaard is `false`, en dat is een afweging.** Aan staan betekent dat
     * elk ICT-incident onder de documentatieplicht valt en dus een motivatie
     * vraagt; dat is precies de wrijving die dit veld moet voorkomen bij de
     * meerderheid die niet Cbw-plichtig is. De prijs is dat een Cbw-plichtige
     * organisatie die dit vergeet om te zetten, de vraag niet gesteld krijgt.
     * Vandaar dat de kennisbank er expliciet naar verwijst: dit is een
     * inrichtingsbeslissing, geen detail.
     *
     * Los van `ISMS_NORM`: ook een ISO 27001-installatie kan Cbw-plichtig zijn,
     * en een zorginstelling hoeft het niet te zijn.
     */
    'cbw_plichtig' => (bool) env('ISMS_CBW_PLICHTIG', false),

    'grondslagen' => [

        'avg' => [
            'label' => 'AVG',
            'toelichting' => 'Inbreuk in verband met persoonsgegevens (datalek).',
            'fasen' => [
                'melding' => [
                    'label' => 'Melding aan de toezichthouder',
                    'grondslag_artikel' => 'AVG art. 33 lid 1',
                    'uren' => 72,
                    'anker' => 'kennisname',
                    // Een melding later dan 72 uur mag, maar gaat vergezeld van
                    // een motivering voor de vertraging (art. 33 lid 1). Die
                    // hoort in `toelichting`.
                    'altijd' => true,
                ],
                'betrokkenen' => [
                    'label' => 'Mededeling aan de betrokkenen',
                    'grondslag_artikel' => 'AVG art. 34 lid 1',
                    'uren' => null,
                    'anker' => 'geen',
                    // Alleen bij een waarschijnlijk hoog risico, en de plicht
                    // kan vervallen door versleuteling of vervolgmaatregelen
                    // (art. 34 lid 3). Dus geen automatische rij.
                    'altijd' => false,
                ],
            ],
        ],

        'cbw' => [
            'label' => 'Cyberbeveiligingswet',
            'toelichting' => 'Significant incident bij een entiteit die onder de Cbw (NIS2) valt.',
            'fasen' => [
                'waarschuwing' => [
                    'label' => 'Vroegtijdige waarschuwing',
                    'grondslag_artikel' => 'Cbw art. 26 lid 1',
                    'uren' => 24,
                    'anker' => 'kennisname',
                    'altijd' => true,
                ],
                'melding' => [
                    'label' => 'Incidentmelding',
                    'grondslag_artikel' => 'Cbw art. 27 lid 1',
                    'uren' => 72,
                    'anker' => 'kennisname',
                    'altijd' => true,
                ],
                'eindverslag' => [
                    'label' => 'Eindverslag',
                    'grondslag_artikel' => 'Cbw art. 29',
                    'uren' => 720, // één maand
                    'anker' => 'melding',
                    'altijd' => true,
                ],
            ],
        ],
    ],

    // Het tussentijdse verslag van Cbw art. 28 staat hier bewust niet bij: het
    // komt op verzoek van het CSIRT of de bevoegde autoriteit en heeft geen
    // termijn, dus er valt niets te bewaken. Dat hoort als bewijsstuk aan het
    // incident.
];
