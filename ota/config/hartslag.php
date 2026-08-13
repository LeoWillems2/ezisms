<?php

/**
 * De schedulerhartslag (implementatie/00m §5).
 *
 * Waarom de klasse hier staat en niet in de code van `isms:controleer-hartslag`:
 * "is een gemiste run in te halen" is een inhoudelijk oordeel over wat een
 * commando doet, geen detectielogica. Het hoort leesbaar te zijn voor wie de
 * uitkomst moet beoordelen, en aanpasbaar zonder het commando te wijzigen.
 *
 * De planning zelf staat níét hier maar in `routes/console.php` — dat blijft de
 * enige bron van wat er wanneer draait (00m §0.1). Deze lijst zegt alleen wat
 * een gemiste run bétekent.
 */
return [

    // Hoe lang een gat mag duren voordat het gemeld wordt. Een gewone upgrade
    // duurt minuten; een etmaal betekent dat er echt een run is overgeslagen.
    'drempel_uren' => 24,

    // Bovengrens op het aantal opgesomde momenten per commando (00m §9.2). Een
    // installatie die een jaar stil lag levert anders honderden momenten op, en
    // de eerste handeling na een lange stilstand hoort geen berg taken te zijn.
    'maximum_momenten' => 500,

    // Dekt de jaarlijkse `isms:leg-restrisico-vast` met marge. Het opruimen
    // spaart altijd de laatste rij per sleutel — zie 00m §1.1.
    'bewaartermijn_dagen' => 400,

    /*
     * Per commando: is een gemiste run in te halen?
     *
     *   inhaalbaar    de volgende run doet het alsnog; melden volstaat
     *   gemengd       deels op te vangen, deels niet — zie §6, alleen KPI's
     *   onherstelbaar wat er gemist is, is niet te reconstrueren ⇒ taak
     *
     * Een commando dat hier NIET in staat geldt als `onherstelbaar`. Dat is met
     * opzet de veilige kant: een nieuw gepland commando waarvan niemand de
     * klasse heeft bepaald moet ruis maken en niet stilzwijgend als onschuldig
     * gelden.
     */
    'commandos' => [

        // Technisch inhaalbaar, inhoudelijk niet onschuldig: bij een gat van zes
        // weken hebben accounts zes weken langer toegang gehad dan bedoeld. Daar
        // valt niets aan te herstellen, en juist daarom wordt het gemeld.
        'isms:verval-gebruikersaccounts' => [
            'klasse' => 'inhaalbaar',
            'let_op' => 'accounts bleven actief ná hun vervaldatum',
        ],

        'isms:archiveer-bewijsstukken' => ['klasse' => 'inhaalbaar'],

        // De herinnering zelf is weg; de afdwinging van de tweede factor niet.
        'isms:herinner-tweefactor' => ['klasse' => 'inhaalbaar'],

        // De volgende controle loopt de hele keten na, niet alleen het nieuwe
        // stuk — een gemiste nacht laat dus geen gat in de dekking achter.
        'isms:controleer-audittrail' => ['klasse' => 'inhaalbaar'],

        // Idempotent; wat vertraging opliep is de escalatie, niet de taak zelf.
        'isms:genereer-taken' => ['klasse' => 'inhaalbaar'],
        'isms:verloop-taken' => ['klasse' => 'inhaalbaar'],

        'isms:schoon-raadplegingen' => ['klasse' => 'inhaalbaar'],

        // Gemengd, en dat is de reden dat deze klasse bestaat: gebeurtenis-KPI's
        // worden opgevangen door een langer venster (12g §3), toestand-KPI's
        // niet — de stand van 1 september is op 20 oktober niet meer op te
        // vragen. Zie 00m §6.
        'isms:meet-kpis' => ['klasse' => 'gemengd'],

        // De restrisico's van dat peiljaar zijn niet te reconstrueren.
        'isms:leg-restrisico-vast' => ['klasse' => 'onherstelbaar'],

        // De hartslagcontrole zelf. Inhaalbaar: hij kijkt terug, dus de
        // eerstvolgende run ziet het gat dat de gemiste run had moeten zien.
        'isms:controleer-hartslag' => ['klasse' => 'inhaalbaar'],
    ],
];
