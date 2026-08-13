<?php

/**
 * Wat de niveaus 1 t/m 5 van kans en impact betekenen
 * (implementatie/00j-beoordelingsschaal-en-vocabulaire.md).
 *
 * ISO 27001 §6.1.2 c) vraagt de gevolgen en de waarschijnlijkheid te bepalen.
 * Zonder definitie is de score geen criterium maar een gevoel: twee mensen die
 * hetzelfde scenario beoordelen komen dan niet op hetzelfde getal uit, en
 * niemand kan later nagaan waarom er een 4 staat.
 *
 * **Dit bestand is de SEEDBRON, niet de bron tijdens het draaien** (04g §7).
 * `RisicocriteriaSeeder` schrijft deze teksten één keer weg als versie 1 van de
 * risicocriteria; daarna leest `App\Support\Beoordelingsschaal` uit de database
 * en stelt de organisatie de schaal zelf vast — met goedkeuring door Management
 * en met een versienummer waar elk beoordeeld risico naar verwijst.
 *
 * Twee gevolgen om te kennen:
 *
 * 1. **Na het seeden verandert een andere `ISMS_NORM` de schaal niet meer.** Dat
 *    is geen nieuwe eigenschap — het geldt voor alle geseede profieldata — maar
 *    het is nieuw voor dít bestand.
 * 2. **Een wijziging hier raakt bestaande installaties niet.** Wie de teksten
 *    hier bijstelt, verandert alleen waar een níeuwe installatie mee begint.
 *
 * **Kans is gedeeld, impact niet.** Hoe vaak iets gebeurt is een frequentie en
 * die is norm-onafhankelijk; wat een gebeurtenis erg maakt is precies waar
 * NEN 7510 §6.1 iets toevoegt — beveiliging, patiëntveiligheid en
 * doeltreffendheid van de zorg wegen daar in samenhang. Dat landt hier in de
 * betekenis van de niveaus en niet in een tweede impact-as: `risicos` houdt één
 * `impact_niveau`, de score blijft kans × impact en de matrix blijft 5×5
 * (nen7510-opzet.md §4.4).
 *
 * De niveaus lopen van 1 t/m `Risicoverdeling::SCHAAL`; `App\Support\Beoordelingsschaal`
 * gooit als er een gat in zit.
 */
return [

    'kans' => [
        'label' => 'Kans',
        'leidraad' => 'Schat hoe vaak het scenario zich voordoet als je niets extra\'s doet — dus '
            .'vóór de behandeling. Gebruik wat je weet uit eigen incidenten, audits en de sector. '
            .'Twijfel je tussen twee niveaus, kies dan het hoogste en schrijf in de motivatie op '
            .'waarom.',
        'niveaus' => [
            1 => [
                'naam' => 'Zeer klein',
                'omschrijving' => 'Geen bekend geval in de eigen organisatie of de sector; er moet '
                    .'veel tegelijk misgaan. Ordegrootte: minder dan eens in de tien jaar.',
            ],
            2 => [
                'naam' => 'Klein',
                'omschrijving' => 'Voorstelbaar zonder bijzondere omstandigheden en het komt in de '
                    .'sector voor. Ordegrootte: eens in de drie tot tien jaar.',
            ],
            3 => [
                'naam' => 'Middelmatig',
                'omschrijving' => 'Is hier eerder gebeurd, of gebeurt regelmatig bij vergelijkbare '
                    .'organisaties. Ordegrootte: ongeveer eens per jaar.',
            ],
            4 => [
                'naam' => 'Groot',
                'omschrijving' => 'Doet zich meerdere keren per jaar voor, of de omstandigheden die '
                    .'het veroorzaken zijn nu aanwezig. Ordegrootte: elk kwartaal.',
            ],
            5 => [
                'naam' => 'Zeer groot',
                'omschrijving' => 'Doet zich maandelijks of vaker voor, of is op dit moment gaande. '
                    .'Uitgaan van "het gebeurt" is realistischer dan van een kans.',
            ],
        ],
    ],

    'impact' => [
        'label' => 'Impact',

        /*
         * Per normprofiel, gesleuteld op de waarde van `norm.actief`. Hetzelfde
         * patroon als `Kennisartikelen::ARTIKELEN[…]['bestand']` (00i): data op
         * profiel gesleuteld, geen `if` op het profiel in de code. Een profiel
         * dat hier ontbreekt gooit — stil terugvallen op ISO zou een
         * zorginstallatie laten scoren op een schaal die de cliënt niet noemt.
         */
        'profielen' => [

            'iso27001' => [
                'leidraad' => 'Weeg de gevolgen voor de bedrijfsvoering, voor de mensen wier '
                    .'persoonsgegevens het betreft, voor wettelijke plichten en voor financiën en '
                    .'reputatie. Scoor de zwaarste van die gevolgen, niet het gemiddelde.',
                'niveaus' => [
                    1 => [
                        'naam' => 'Verwaarloosbaar',
                        'omschrijving' => 'Hinder binnen één team, dezelfde dag verholpen. Geen '
                            .'persoonsgegevens betrokken, geen meldplicht, geen aantoonbare schade.',
                    ],
                    2 => [
                        'naam' => 'Klein',
                        'omschrijving' => 'Eén proces ligt korte tijd stil of levert onbetrouwbare '
                            .'uitkomsten; herstel binnen een week met eigen mensen. Hooguit interne '
                            .'gegevens betrokken.',
                    ],
                    3 => [
                        'naam' => 'Middelmatig',
                        'omschrijving' => 'Meerdere processen geraakt, of persoonsgegevens van een '
                            .'beperkte groep betrokken. Melden aan de toezichthouder komt in beeld; '
                            .'herstel kost weken en geld buiten de begroting.',
                    ],
                    4 => [
                        'naam' => 'Groot',
                        'omschrijving' => 'Een kernproces ligt langdurig stil, of gegevens van een '
                            .'grote groep zijn gelekt of onbetrouwbaar geworden. Meldplicht aan de '
                            .'toezichthouder en aan de betrokkenen; klantverlies, contractboetes en '
                            .'publiciteit.',
                    ],
                    5 => [
                        'naam' => 'Zeer groot',
                        'omschrijving' => 'Onomkeerbaar verlies van informatie of langdurige uitval '
                            .'van wat de organisatie ís, of schade aan betrokkenen die niet meer te '
                            .'herstellen is. Het voortbestaan of de vergunning staat op het spel.',
                    ],
                ],
            ],

            'nen7510' => [
                'leidraad' => 'NEN 7510 §6.1 vraagt beveiliging, patiëntveiligheid en '
                    .'doeltreffendheid van de zorg in samenhang te wegen. Deze schaal doet dat op '
                    .'één as: elk niveau noemt zowel het gevolg voor de organisatie als het gevolg '
                    .'voor de cliënt. Scoor de zwaarste van de twee, en schrijf in de motivatie op '
                    .'wélke van beide de score bepaalde — dat is wat een auditor hier zoekt.',
                'niveaus' => [
                    1 => [
                        'naam' => 'Verwaarloosbaar',
                        'omschrijving' => 'Hinder binnen één team, dezelfde dag verholpen. De '
                            .'zorgverlening merkt er niets van en er is geen persoonlijke '
                            .'gezondheidsinformatie betrokken.',
                    ],
                    2 => [
                        'naam' => 'Klein',
                        'omschrijving' => 'Een ondersteunend proces ligt korte tijd stil; de zorg '
                            .'loopt door, hooguit met een omweg. Geen cliënt ondervindt gevolgen.',
                    ],
                    3 => [
                        'naam' => 'Middelmatig',
                        'omschrijving' => 'De zorgverlening is merkbaar verstoord: afspraken lopen '
                            .'uit of gegevens moeten langs een andere weg worden opgezocht. '
                            .'Persoonlijke gezondheidsinformatie van een beperkt aantal cliënten is '
                            .'betrokken. Geen gezondheidsschade.',
                    ],
                    4 => [
                        'naam' => 'Groot',
                        'omschrijving' => 'De behandeling van meerdere cliënten wordt uitgesteld of '
                            .'berust op onvolledige of onjuiste gegevens, met gezondheidsschade die '
                            .'te herstellen is. Of: persoonlijke gezondheidsinformatie van een grote '
                            .'groep is gelekt, met meldplicht aan de toezichthouder en aan de '
                            .'betrokkenen.',
                    ],
                    5 => [
                        'naam' => 'Zeer groot',
                        'omschrijving' => 'Onomkeerbare schade aan de gezondheid van een cliënt, of '
                            .'overlijden. Of: de zorgverlening valt zo lang uit dat cliënten elders '
                            .'moeten worden ondergebracht. Op dit niveau weegt de veiligheid van de '
                            .'cliënt zwaarder dan elk bedrijfsbelang.',
                    ],
                ],
            ],
        ],
    ],
];
