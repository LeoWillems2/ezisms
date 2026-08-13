<?php

/**
 * Het schema van de maatregelclassificatie: welke dimensies er zijn, welke
 * waarden daarin voorkomen en waar dat vocabulaire vandaan komt.
 *
 * Dit bestand is de enige bron voor die drie vragen. De weergave, de validatie,
 * de export en de vocabulairebewaking in de tests lezen hier; er is bewust geen
 * tweede lijst die kan gaan afwijken.
 *
 * `actief` is het enige veld waar code op stuurt. Alles wat over dimensies
 * loopt, filtert op `actief === true` — één plek dus om een dimensie aan of uit
 * te zetten.
 *
 * `herkomst` is geen sier maar de verantwoording: het ISMS levert een
 * uitgangsclassificatie mee, geen normtabel, en die herkomst hoort zichtbaar te
 * zijn in de UI en in de export.
 *
 * `waarden_extra` is optioneel en gesleuteld op een capaciteit uit
 * `config/norm.php`: waarden die alleen in een installatie met die capaciteit
 * bij het vocabulaire horen, met hun eigen zin bij de herkomst. Zie
 * `App\Support\Maatregelkenmerken` en implementatie/00j §2.
 */
return [
    'type' => [
        'label' => 'Type',
        'toelichting' => 'Werkt de maatregel vóór, tijdens of ná een gebeurtenis?',
        'actief' => true,
        'herkomst' => 'Algemene beveiligingsindeling, ouder dan ISO 27002.',
        'waarden' => ['Preventief', 'Detectief', 'Corrigerend'],
    ],

    'eigenschappen' => [
        'label' => 'Eigenschappen',
        'toelichting' => 'Welke kernwaarden beschermt de maatregel?',
        'actief' => true,
        'herkomst' => 'De CIA-driehoek; universeel vakgebied-vocabulaire.',
        'waarden' => ['Vertrouwelijkheid', 'Integriteit', 'Beschikbaarheid'],

        /*
         * Drie waarden erbij in een installatie die de capaciteit
         * `zorgterminologie` heeft (implementatie/00j §2.1). Gesleuteld op
         * capaciteit en niet op normprofiel: de vraag is of deze installatie dit
         * vocabulaire kent, niet wie ze is — zie App\Support\Normprofiel.
         *
         * Dit is vocabulaire en geen normtekst: de drie begrippen staan in
         * ISO/IEC 27000 en zijn via ISO 27799 het zorgdomein in gekomen. De
         * herkomst hoort daarom in het `herkomst`-veld, dat in de UI en in de
         * export zichtbaar is — drie losse waarden zonder verantwoording zijn
         * precies de "plausibele invulling" waar plan 04d voor waarschuwt.
         */
        'waarden_extra' => [
            'zorgterminologie' => [
                'waarden' => ['Authenticiteit', 'Onweerlegbaarheid', 'Controleerbaarheid'],
                'herkomst' => 'Aangevuld met authenticiteit, onweerlegbaarheid en '
                    .'controleerbaarheid; die drie horen bij de weging die NEN 7510 vraagt '
                    .'(ISO 27799 via NEN 7510).',
            ],
        ],
    ],

    'concepten' => [
        'label' => 'Concepten',
        'toelichting' => 'In welke fase van de beveiligingscyclus grijpt de maatregel aan?',
        'actief' => true,
        'herkomst' => 'De vijf functies van het NIST Cybersecurity Framework '
            .'(Amerikaans overheidswerk, vrij te gebruiken).',
        'waarden' => ['Identificeren', 'Beschermen', 'Detecteren', 'Reageren', 'Herstellen'],
    ],

    'domeinen' => [
        'label' => 'Domeinen',
        'toelichting' => 'Op welk vakgebied binnen informatiebeveiliging ligt de maatregel?',
        'actief' => true,
        'herkomst' => 'Europese cyberbeveiligingstaxonomie (ECSO/ERNCIP).',
        'waarden' => ['Governance en Ecosysteem', 'Bescherming', 'Verdediging', 'Veerkracht'],
    ],

    // Bekend, bewust niet ingevuld. ISO 27002 kent deze dimensie, maar zowel de
    // waardenlijst als de toewijzing per maatregel zijn zonder de norm niet te
    // verantwoorden — en dit is de enige dimensie waarvan óók het vocabulaire
    // ISO-eigen is. Hij staat hier zodat duidelijk is dat hij niet vergeten is.
    //
    // Het repo levert er dus niets bij: geen waarden, geen toewijzing, en de
    // schakelaar staat standaard uit. Een plausibele invulling zou hier erger
    // zijn dan een lege — die is namelijk niet van een correcte te
    // onderscheiden.
    //
    // Wie de norm wél bezit, zet hem aan in de eigen installatie met
    // `php artisan isms:capaciteiten aan`. Dat commando leest de waarden en de
    // toewijzing uit `database/seeders/data/maatregel-capaciteiten.json` — een
    // lokaal bestand dat gitignored is, net als `controls.json`. Zet de
    // schakelaar nooit met de hand op `true`: dan
    // is de dimensie wel actief maar leeg, en dan klopt er niets van.
    'capaciteiten' => [
        'label' => 'Capaciteiten',
        'toelichting' => 'Alleen beschikbaar als je de norm bezit; zie isms:capaciteiten.',
        'actief' => env('ISMS_CAPACITEITEN', false),
        'herkomst' => 'Eigen indeling van ISO 27002; niet meegeleverd.',
        // Leeg in het repo. Het vocabulaire komt uit het lokale bronbestand
        // hieronder, en alleen zolang de dimensie actief is.
        'waarden' => [],
        'bron' => 'maatregel-capaciteiten.json',
    ],
];
