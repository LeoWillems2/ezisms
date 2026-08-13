<?php

namespace App\Support;

use App\Models\Sjabloonstap;
use App\Models\Wijzigingssjabloon;

/**
 * De meegeleverde wijzigingsroutes (implementatie/15 §9 en §19).
 *
 * Eén bron voor twee gebruikers: `WijzigingssjabloonSeeder` zet ze bij een verse
 * installatie neer, en `zetTerug()` herstelt een route die de organisatie heeft
 * aangepast. Twee kopieën van dezelfde stappenlijst zouden onvermijdelijk uit de
 * pas gaan lopen — en dan herstelt de knop naar iets anders dan er geleverd is.
 *
 * De teksten zijn eigen omschrijvingen met alleen maatregelnummers als
 * verwijzing; géén normtekst overnemen.
 */
final class Wijzigingsroutes
{
    /** @return list<array<string, mixed>> */
    public static function alle(): array
    {
        return [
            [
                'naam' => 'Leveranciersrelease — standaard',
                'omschrijving' => 'De gebruikelijke route voor een aangekondigde update of upgrade van een '
                    .'ingekochte dienst. Dekt A.8.32 a) tot en met g).',
                'soort' => 'leveranciersrelease',
                'zwaarte' => 'standaard',
                'stappen' => [
                    [
                        'volgorde' => 1, 'staptype' => 'analyse', 'deadline_offset_dagen' => -10,
                        'titel' => 'Release notes en acceptatie beoordelen',
                        'omschrijving' => 'Beoordeel de aangekondigde wijzigingen en toets ze op de '
                            .'acceptatieomgeving. Koppel de release notes en het testresultaat als bewijs (A.8.32 d).',
                        'bewijs_verplicht' => true,
                    ],
                    [
                        'volgorde' => 2, 'staptype' => 'analyse', 'deadline_offset_dagen' => -7,
                        'titel' => 'Impactanalyse, inclusief downtime',
                        'omschrijving' => 'Breng de gevolgen in kaart: downtime, afhankelijkheden, gewijzigde '
                            .'werking, licenties (A.8.32 a). Leg het terugvalplan vast.',
                    ],
                    [
                        'volgorde' => 2, 'staptype' => 'informeren', 'deadline_offset_dagen' => -7,
                        'titel' => 'Belanghebbenden informeren',
                        'omschrijving' => 'Informeer de betrokken gebruikers over het moment, de verwachte '
                            .'onderbreking en wat er verandert (A.8.32 c).',
                    ],
                    [
                        'volgorde' => 3, 'staptype' => 'goedkeuring', 'deadline_offset_dagen' => -3,
                        'titel' => 'Wijziging autoriseren',
                        'omschrijving' => 'Formele toestemming om de wijziging door te voeren (A.8.32 b). '
                            .'Bij afkeuring gaat de reeks terug naar de beoordeling.',
                        'bij_afkeuren_terug_naar' => 1,
                    ],
                    [
                        'volgorde' => 4, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 0,
                        'titel' => 'Uitvoeren volgens inzetplan',
                        'omschrijving' => 'Doorvoeren van de wijziging (A.8.32 e). Kan pas als het terugvalplan '
                            .'is vastgelegd.',
                    ],
                    [
                        'volgorde' => 5, 'staptype' => 'evaluatie', 'deadline_offset_dagen' => 7,
                        'titel' => 'Evalueren en afsluiten',
                        'omschrijving' => 'Is de wijziging geslaagd, is er teruggedraaid, wat kan er beter? '
                            .'Sluit het dossier af (A.8.32 g).',
                    ],
                ],
            ],
            [
                'naam' => 'Leveranciersrelease — ingrijpend',
                'omschrijving' => 'Voor releases met grote gevolgen: extra toetsing van het terugvalplan en '
                    .'bijwerken van documentatie en continuïteitsplannen (A.8.32 h en i).',
                'soort' => 'leveranciersrelease',
                'zwaarte' => 'ingrijpend',
                'stappen' => [
                    [
                        'volgorde' => 1, 'staptype' => 'analyse', 'deadline_offset_dagen' => -14,
                        'titel' => 'Release notes en acceptatie beoordelen',
                        'omschrijving' => 'Zoals bij de standaardroute, met een ruimere doorlooptijd.',
                        'bewijs_verplicht' => true,
                    ],
                    [
                        'volgorde' => 2, 'staptype' => 'analyse', 'deadline_offset_dagen' => -10,
                        'titel' => 'Impactanalyse, inclusief downtime',
                        'omschrijving' => 'Gevolgen, afhankelijkheden en licenties in kaart (A.8.32 a).',
                    ],
                    [
                        'volgorde' => 3, 'staptype' => 'analyse', 'deadline_offset_dagen' => -5,
                        'titel' => 'Terugvalplan toetsen',
                        'omschrijving' => 'Is het vangnet werkelijk uitvoerbaar binnen de afgesproken tijd? '
                            .'Leg de uitkomst van de toets vast als bewijs (A.8.32 f).',
                        'bewijs_verplicht' => true,
                    ],
                    [
                        'volgorde' => 4, 'staptype' => 'informeren', 'deadline_offset_dagen' => -5,
                        'titel' => 'Belanghebbenden informeren',
                        'omschrijving' => 'Bericht aan de betrokken gebruikers over moment en gevolgen (A.8.32 c).',
                    ],
                    [
                        'volgorde' => 5, 'staptype' => 'goedkeuring', 'deadline_offset_dagen' => -3,
                        'titel' => 'Wijziging autoriseren',
                        'omschrijving' => 'Formele toestemming (A.8.32 b). Bij afkeuring terug naar de impactanalyse.',
                        'bij_afkeuren_terug_naar' => 2,
                    ],
                    [
                        'volgorde' => 6, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 0,
                        'titel' => 'Uitvoeren volgens inzetplan',
                        'omschrijving' => 'Doorvoeren van de wijziging (A.8.32 e).',
                    ],
                    [
                        'volgorde' => 7, 'staptype' => 'analyse', 'deadline_offset_dagen' => 3,
                        'titel' => 'Bedieningsdocumentatie bijwerken',
                        'omschrijving' => 'Werkinstructies en gebruikersprocedures aanpassen aan de nieuwe '
                            .'werking (A.8.32 h).',
                    ],
                    [
                        'volgorde' => 7, 'staptype' => 'analyse', 'deadline_offset_dagen' => 3,
                        'titel' => 'Continuïteitsplan bijwerken',
                        'omschrijving' => 'Herstel- en responsprocedures aanpassen zolang ze nog passend moeten '
                            .'blijven (A.8.32 i).',
                    ],
                    [
                        'volgorde' => 8, 'staptype' => 'evaluatie', 'deadline_offset_dagen' => 10,
                        'titel' => 'Evalueren en afsluiten',
                        'omschrijving' => 'Uitkomst, eventuele terugdraaiing en verbeterpunten vastleggen (A.8.32 g).',
                    ],
                ],
            ],
            [
                'naam' => 'Ingebruikname van een systeem of dienst',
                'omschrijving' => 'Iets nieuws in productie nemen. De zwaarste route aan de voorkant: de '
                    .'beveiligingseisen, de leveranciersbeoordeling en de acceptatietest liggen allemaal vóór de '
                    .'goedkeuring, want daarna is bijsturen duur.',
                'soort' => 'ingebruikname',
                'zwaarte' => 'ingrijpend',
                'stappen' => [
                    [
                        'volgorde' => 1, 'staptype' => 'analyse', 'deadline_offset_dagen' => -30,
                        'titel' => 'Beveiligingseisen en classificatie bepalen',
                        'omschrijving' => 'Welke gegevens gaan erin, hoe zijn die geclassificeerd en welke eisen '
                            .'volgen daaruit? (A.5.12, A.8.26)',
                    ],
                    [
                        'volgorde' => 1, 'staptype' => 'analyse', 'deadline_offset_dagen' => -30,
                        'titel' => 'Leverancier beoordelen en afspraken vastleggen',
                        'omschrijving' => 'Bij een ingekochte dienst: de leverancier opvoeren en beoordelen, en de '
                            .'securityrelevante clausules vastleggen (A.5.19 t/m A.5.23).',
                    ],
                    [
                        'volgorde' => 2, 'staptype' => 'analyse', 'deadline_offset_dagen' => -21,
                        'titel' => "Risico's beoordelen en behandelen",
                        'omschrijving' => 'Wat brengt dit systeem aan nieuwe risico\'s mee, en wat doen we ermee? '
                            .'Leg ze vast in het risicoregister.',
                    ],
                    [
                        'volgorde' => 3, 'staptype' => 'analyse', 'deadline_offset_dagen' => -14,
                        'titel' => 'Acceptatietest uitvoeren en vastleggen',
                        'omschrijving' => 'Testen op een omgeving die van productie gescheiden is, inclusief de '
                            .'beveiligingsfuncties. Koppel het testverslag als bewijs (A.8.29, A.8.31).',
                        'bewijs_verplicht' => true,
                    ],
                    [
                        'volgorde' => 4, 'staptype' => 'goedkeuring', 'deadline_offset_dagen' => -7,
                        'titel' => 'Ingebruikname autoriseren',
                        'omschrijving' => 'Formele toestemming om in productie te gaan (A.8.32 b). Bij afkeuring '
                            .'terug naar de eisen.',
                        'bij_afkeuren_terug_naar' => 1,
                    ],
                    [
                        'volgorde' => 5, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 0,
                        'titel' => 'In productie nemen',
                        'omschrijving' => 'De feitelijke ingebruikname. Ook hier geldt de terugvaleis: wat doen we '
                            .'als het niet werkt?',
                    ],
                    [
                        'volgorde' => 6, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 1,
                        'titel' => 'Toegangsrechten inrichten',
                        'omschrijving' => 'Wie krijgt waar toegang toe, en wie beheert dat? Leg het toegangsmodel '
                            .'vast bij de start in plaats van het te laten groeien (A.5.15, A.5.18).',
                    ],
                    [
                        'volgorde' => 7, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 3,
                        'titel' => 'Back-up, monitoring en continuïteit inregelen',
                        'omschrijving' => 'Een systeem zonder back-up of monitoring is pas bij het eerste incident '
                            .'een probleem, en dan is het te laat (A.8.13, A.8.16, A.5.30).',
                    ],
                    [
                        'volgorde' => 7, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 3,
                        'titel' => 'Asset- en systeemregister bijwerken',
                        'omschrijving' => 'Het systeem en de bijbehorende assets opvoeren met eigenaar en '
                            .'classificatie (A.5.9).',
                    ],
                    [
                        'volgorde' => 8, 'staptype' => 'evaluatie', 'deadline_offset_dagen' => 14,
                        'titel' => 'Evalueren en afsluiten',
                        'omschrijving' => 'Werkt het zoals bedoeld, en is alles uit deze route daadwerkelijk '
                            .'ingeregeld? (A.8.32 g)',
                    ],
                ],
            ],
            [
                'naam' => 'Configuratiewijziging',
                'omschrijving' => 'Een wijziging aan de instellingen van een systeem, dienst of netwerk. Licht van '
                    .'opzet, maar wel door het proces: A.8.9 zegt met zoveel woorden dat configuratiewijzigingen '
                    .'het wijzigingsbeheerproces horen te volgen.',
                'soort' => 'configuratie',
                'zwaarte' => 'standaard',
                'stappen' => [
                    [
                        'volgorde' => 1, 'staptype' => 'analyse', 'deadline_offset_dagen' => -7,
                        'titel' => 'Impact en afhankelijkheden bepalen',
                        'omschrijving' => 'Wat raakt deze instelling, en waar hangt iets anders ervan af? Een '
                            .'ogenschijnlijk kleine instelling kan een grote uitwerking hebben (A.8.32 a).',
                    ],
                    [
                        'volgorde' => 1, 'staptype' => 'analyse', 'deadline_offset_dagen' => -7,
                        'titel' => 'Terugvalpad vaststellen',
                        'omschrijving' => 'Hoe komen we terug bij de vorige instelling, en hoe snel? Leg het '
                            .'terugvalplan vast op het dossier (A.8.32 f, A.8.19 f).',
                    ],
                    [
                        'volgorde' => 2, 'staptype' => 'informeren', 'deadline_offset_dagen' => -3,
                        'titel' => 'Betrokkenen informeren als de werking verandert',
                        'omschrijving' => 'Merkt niemand er iets van, rond de stap dan af met die vaststelling — '
                            .'dat is ook een antwoord op A.8.32 c).',
                    ],
                    [
                        'volgorde' => 3, 'staptype' => 'goedkeuring', 'deadline_offset_dagen' => -2,
                        'titel' => 'Wijziging autoriseren',
                        'omschrijving' => 'Formele toestemming (A.8.32 b). Bij afkeuring terug naar de impact.',
                        'bij_afkeuren_terug_naar' => 1,
                    ],
                    [
                        'volgorde' => 4, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 0,
                        'titel' => 'Configuratie doorvoeren',
                        'omschrijving' => 'De wijziging aanbrengen (A.8.32 e).',
                    ],
                    [
                        'volgorde' => 5, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 1,
                        'titel' => 'Configuratieregistratie bijwerken',
                        'omschrijving' => 'A.8.9 vraagt een logbestand van alle configuratiewijzigingen. Zonder '
                            .'deze stap loopt de registratie achter op de werkelijkheid.',
                    ],
                    [
                        'volgorde' => 6, 'staptype' => 'analyse', 'deadline_offset_dagen' => 1,
                        'titel' => 'Werking en monitoring verifiëren',
                        'omschrijving' => 'Doet het wat het moet doen, en ziet de monitoring de nieuwe situatie? '
                            .'(A.8.16)',
                    ],
                    [
                        'volgorde' => 7, 'staptype' => 'evaluatie', 'deadline_offset_dagen' => 5,
                        'titel' => 'Evalueren en afsluiten',
                        'omschrijving' => 'Had de wijziging het beoogde effect, en zijn er bijwerkingen? (A.8.32 g)',
                    ],
                ],
            ],
            [
                'naam' => 'Infrastructuurwijziging',
                'omschrijving' => 'Wijzigingen aan netwerk, hardware of hosting die niet van een leverancier '
                    .'komen. Het zwaartepunt ligt op beschikbaarheid: een onderhoudsvenster, een terugvalpad en '
                    .'een bijgewerkte configuratieregistratie.',
                'soort' => 'infrastructuur',
                'zwaarte' => 'standaard',
                'stappen' => [
                    [
                        'volgorde' => 1, 'staptype' => 'analyse', 'deadline_offset_dagen' => -14,
                        'titel' => 'Impact op beschikbaarheid en afhankelijkheden bepalen',
                        'omschrijving' => 'Welke systemen hangen hieraan, wat is hun beschikbaarheidseis en wat '
                            .'valt er weg tijdens de wijziging? (A.8.32 a, A.8.14)',
                    ],
                    [
                        'volgorde' => 1, 'staptype' => 'analyse', 'deadline_offset_dagen' => -14,
                        'titel' => 'Onderhoudsvenster en terugvalpad vaststellen',
                        'omschrijving' => 'Wanneer mag het, hoe lang mag het duren en hoe komen we terug als het '
                            .'misgaat? Leg het terugvalplan vast op het dossier (A.8.32 f).',
                    ],
                    [
                        'volgorde' => 2, 'staptype' => 'informeren', 'deadline_offset_dagen' => -7,
                        'titel' => 'Belanghebbenden informeren over het onderhoudsvenster',
                        'omschrijving' => 'Wie merkt er iets van, en wanneer? (A.8.32 c)',
                    ],
                    [
                        'volgorde' => 3, 'staptype' => 'goedkeuring', 'deadline_offset_dagen' => -3,
                        'titel' => 'Wijziging autoriseren',
                        'omschrijving' => 'Formele toestemming, inclusief het gekozen venster (A.8.32 b).',
                        'bij_afkeuren_terug_naar' => 1,
                    ],
                    [
                        'volgorde' => 4, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 0,
                        'titel' => 'Uitvoeren binnen het onderhoudsvenster',
                        'omschrijving' => 'Doorvoeren van de wijziging (A.8.32 e).',
                    ],
                    [
                        'volgorde' => 5, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 1,
                        'titel' => 'Configuratieregistratie bijwerken',
                        'omschrijving' => 'A.8.9 vraagt een logbestand van alle configuratiewijzigingen. Zonder '
                            .'deze stap loopt de registratie stil achter op de werkelijkheid.',
                    ],
                    [
                        'volgorde' => 6, 'staptype' => 'analyse', 'deadline_offset_dagen' => 2,
                        'titel' => 'Werking en monitoring verifiëren',
                        'omschrijving' => 'Draait alles weer, en zien de monitoring en de back-up de nieuwe '
                            .'situatie? (A.8.16)',
                    ],
                    [
                        'volgorde' => 7, 'staptype' => 'evaluatie', 'deadline_offset_dagen' => 7,
                        'titel' => 'Evalueren en afsluiten',
                        'omschrijving' => 'Bleef het binnen het venster, en wat kan er beter? (A.8.32 g)',
                    ],
                ],
            ],
            [
                'naam' => 'Afvoer van een systeem of dienst',
                'omschrijving' => 'Het uitfaseren van een systeem of ingekochte dienst. De zwaarste route, omdat '
                    .'hier het meeste onopgemerkt blijft liggen: toegang die blijft bestaan, gegevens die bij de '
                    .'leverancier achterblijven en registers die niet worden bijgewerkt.',
                'soort' => 'afvoer',
                'zwaarte' => 'ingrijpend',
                'stappen' => [
                    [
                        'volgorde' => 1, 'staptype' => 'analyse', 'deadline_offset_dagen' => -30,
                        'titel' => 'Impact, afhankelijkheden en koppelingen in kaart brengen',
                        'omschrijving' => 'Welke processen leunen hierop, welke koppelingen vallen weg en wat komt '
                            .'ervoor in de plaats? (A.8.32 a)',
                    ],
                    [
                        'volgorde' => 1, 'staptype' => 'analyse', 'deadline_offset_dagen' => -30,
                        'titel' => 'Bewaartermijn en exportbehoefte vaststellen',
                        'omschrijving' => 'Welke gegevens moeten mee, hoe lang moeten ze bewaard blijven en wat mag '
                            .'weg? Dit bepaalt de stappen na de buitengebruikstelling.',
                    ],
                    [
                        'volgorde' => 2, 'staptype' => 'informeren', 'deadline_offset_dagen' => -21,
                        'titel' => 'Belanghebbenden informeren over de buitengebruikstelling',
                        'omschrijving' => 'Gebruikers weten wanneer het systeem verdwijnt en waar ze daarna terecht '
                            .'kunnen (A.8.32 c).',
                    ],
                    [
                        'volgorde' => 3, 'staptype' => 'goedkeuring', 'deadline_offset_dagen' => -14,
                        'titel' => 'Afvoer autoriseren',
                        'omschrijving' => 'Formele toestemming om het systeem uit te faseren (A.8.32 b). Bij '
                            .'afkeuring terug naar de impactanalyse.',
                        'bij_afkeuren_terug_naar' => 1,
                    ],
                    [
                        'volgorde' => 4, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 0,
                        'titel' => 'Buitengebruikstelling uitvoeren',
                        'omschrijving' => 'Het systeem gaat uit de lucht. Kan pas als het terugvalplan is '
                            .'vastgelegd — ook een afvoer kan misgaan (A.8.32 e en f).',
                    ],
                    [
                        'volgorde' => 5, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 1,
                        'titel' => 'Toegangsrechten intrekken',
                        'omschrijving' => 'Accounts en koppelsleutels die alleen voor dit systeem bestonden, '
                            .'vervallen. Toegang die blijft bestaan is het meest voorkomende restant.',
                    ],
                    [
                        'volgorde' => 6, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 7,
                        'titel' => 'Gegevens exporteren of vernietigen en dat bevestigen',
                        'omschrijving' => 'De export is veiliggesteld en wat weg mag is aantoonbaar vernietigd. '
                            .'Koppel de bevestiging als bewijs.',
                        'bewijs_verplicht' => true,
                    ],
                    [
                        'volgorde' => 6, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 7,
                        'titel' => 'Contract beëindigen en teruggave door de leverancier bevestigen',
                        'omschrijving' => 'Bij een ingekochte dienst: het contract eindigt en de leverancier '
                            .'bevestigt dat hij geen gegevens meer houdt. Werk ook het leveranciersregister bij.',
                    ],
                    [
                        'volgorde' => 7, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 10,
                        'titel' => 'Asset- en systeemregister bijwerken',
                        'omschrijving' => 'Zet het systeem op afgevoerd en de bijbehorende assets op afgestoten. '
                            .'Zonder deze stap blijft het register een systeem tonen dat niet meer bestaat.',
                    ],
                    [
                        'volgorde' => 8, 'staptype' => 'evaluatie', 'deadline_offset_dagen' => 21,
                        'titel' => 'Evalueren en afsluiten',
                        'omschrijving' => 'Is alles daadwerkelijk afgesloten, en wat bleef er langer liggen dan '
                            .'bedoeld? (A.8.32 g)',
                    ],
                ],
            ],
            [
                'naam' => 'Spoedwijziging',
                'omschrijving' => 'Voor wijzigingen die niet kunnen wachten op de gewone route. De goedkeuring '
                    .'vervalt niet maar verschuift naar achteraf — de vorm die A.8.32 f) bedoelt met '
                    .'nood- en voorzorgsoverwegingen.',
                'soort' => 'configuratie',
                'zwaarte' => 'spoed',
                'stappen' => [
                    [
                        'volgorde' => 1, 'staptype' => 'uitvoeren', 'deadline_offset_dagen' => 0,
                        'titel' => 'Uitvoeren',
                        'omschrijving' => 'Doorvoeren van de spoedwijziging. Ook hier geldt: eerst het '
                            .'terugvalplan vastleggen.',
                    ],
                    [
                        'volgorde' => 2, 'staptype' => 'goedkeuring', 'deadline_offset_dagen' => 2,
                        'titel' => 'Goedkeuring achteraf',
                        'omschrijving' => 'Beoordeel achteraf of de spoedroute terecht is gebruikt en of de '
                            .'wijziging mag blijven staan (A.8.32 b en f).',
                    ],
                    [
                        'volgorde' => 3, 'staptype' => 'evaluatie', 'deadline_offset_dagen' => 7,
                        'titel' => 'Evalueren en afsluiten',
                        'omschrijving' => 'Wat maakte de spoed nodig, en is dat te voorkomen? (A.8.32 g)',
                    ],
                ],
            ],
        ];
    }

    /** De definitie van één geleverde route, of null als de naam niet geleverd is. */
    public static function voor(string $naam): ?array
    {
        foreach (self::alle() as $route) {
            if ($route['naam'] === $naam) {
                return $route;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function namen(): array
    {
        return array_column(self::alle(), 'naam');
    }

    /**
     * Wijkt dit sjabloon af van wat er geleverd is?
     *
     * Vergelijkt de eigenschappen die het gedrag bepalen, niet de vormgeving:
     * een gewijzigde omschrijving telt mee, want die vertelt de uitvoerder wat
     * er van hem verwacht wordt.
     */
    public static function wijktAf(Wijzigingssjabloon $sjabloon): bool
    {
        $route = self::voor($sjabloon->naam);

        if ($route === null) {
            return false;
        }

        if ($sjabloon->soort !== $route['soort'] || $sjabloon->zwaarte !== $route['zwaarte']) {
            return true;
        }

        $geleverd = collect($route['stappen'])->map(fn (array $stap) => self::kenmerk($stap))->sort()->values();
        $huidig = $sjabloon->stappen->map(fn (Sjabloonstap $stap) => self::kenmerk([
            'volgorde' => $stap->volgorde,
            'titel' => $stap->titel,
            'omschrijving' => $stap->omschrijving,
            'staptype' => $stap->staptype,
            'deadline_offset_dagen' => $stap->deadline_offset_dagen,
            'bewijs_verplicht' => $stap->bewijs_verplicht,
            'bij_afkeuren_terug_naar' => $stap->bij_afkeuren_terug_naar,
        ]))->sort()->values();

        return $geleverd->all() !== $huidig->all();
    }

    /**
     * Zet een geleverde route terug naar de geleverde stappen.
     *
     * De bestaande stappen gaan eruit en worden opnieuw aangemaakt. Dat raakt
     * lopende dossiers niet: die dragen hun staptype en eisen bevroren op de
     * taak (§17); alleen de herkomstverwijzing `sjabloonstap_id` vervalt.
     */
    public static function zetTerug(Wijzigingssjabloon $sjabloon): void
    {
        $route = self::voor($sjabloon->naam);

        if ($route === null) {
            throw new \RuntimeException('Dit is geen meegeleverde route; er is niets om naar terug te zetten.');
        }

        $sjabloon->update([
            'omschrijving' => $route['omschrijving'],
            'soort' => $route['soort'],
            'zwaarte' => $route['zwaarte'],
        ]);

        foreach ($sjabloon->stappen as $stap) {
            // `delete()` op het model, niet `deleteGeaudit()`: die laatste is een
            // query-builder-macro en zou via __call op een verse query belanden —
            // en dus élke sjabloonstap verwijderen. Het model-event volstaat: de
            // trait schrijft de trailregel op `deleted`.
            $stap->delete();
        }

        self::legStappenVast($sjabloon, $route['stappen']);

        $sjabloon->load('stappen');
    }

    /** @param  list<array<string, mixed>>  $stappen */
    public static function legStappenVast(Wijzigingssjabloon $sjabloon, array $stappen): void
    {
        foreach ($stappen as $stap) {
            Sjabloonstap::create($stap + ['wijzigingssjabloon_id' => $sjabloon->id]);
        }
    }

    /** Eén stap als vergelijkbare tekenreeks; null en false horen hetzelfde te tellen. */
    private static function kenmerk(array $stap): string
    {
        return implode('|', [
            $stap['volgorde'],
            $stap['titel'],
            $stap['omschrijving'] ?? '',
            $stap['staptype'],
            $stap['deadline_offset_dagen'] ?? 0,
            ($stap['bewijs_verplicht'] ?? false) ? '1' : '0',
            $stap['bij_afkeuren_terug_naar'] ?? '',
        ]);
    }
}
