# EzISMS voor de externe auditor: een rondleiding

Een auditor ziet veel ISMS'en, maar dit systeem is waarschijnlijk onbekend. Deze
pagina dient als korte inwerking van ongeveer een kwartier. De pagina beschrijft
hoe het geheel is ingedeeld, waar elke clausule terechtkomt, hoe het bewijs is
vastgelegd en wat het systeem bewust niet doet. Technische kennis is niet nodig.

## Het model in vier regels

1. **Eén organisatie per installatie.** Wat er te zien is, is het hele ISMS. Er
   is geen andere omgeving en geen tweede administratie.
2. **Alles is een register.** Elk onderwerp is een lijst met records die naar
   elkaar verwijzen. Dat geldt voor risico's, maatregelen, beleid, incidenten,
   leveranciers en audits.
3. **Bewijs hangt aan het record**, niet in een aparte map. Eén bewijsstuk kan aan
   meerdere records gekoppeld zijn.
4. **Elke wijziging komt in de audit trail**: wie, wat, wanneer, op welk gebied,
   met de oude en de nieuwe waarde.

## Het auditoraccount

Een auditor krijgt een account met de rol **Auditor**. Dat account heeft de
volgende eigenschappen:

- **Leesrecht op vrijwel alles**, inclusief de audit trail en de bewijsstukken.
- **Geen enkel muteerrecht.** Het account kan niets aanpassen, ook niet per
  ongeluk. Als er iets zichtbaar moet worden wat achter een handeling zit, voert
  de CISO die handeling uit terwijl de auditor meekijkt. Die handeling komt dan
  gewoon in de audit trail.
- **Eén uitzondering op "leest alles":** een interne auditor die aan een
  auditronde is toegewezen, mag in die ronde bevindingen vastleggen. Bij een
  externe certificeringsaudit is dat niet de bedoeling. Daar blijft het
  auditrapport de bron van waarheid en neemt de CISO de bevindingen over.

Links in het scherm staat het menu. De inhoud van het menu volgt de rechten van
het account, dus onderdelen zonder inzage worden niet getoond.

## Waar wat te vinden is

De navigatie volgt de opbouw van het managementsysteem en niet de nummering van
de norm. De onderstaande tabel geeft de vertaling tussen beide.

| Onderwerp | Clausule | Menu |
| --- | --- | --- |
| Context, issues, belanghebbenden, scope | 4.1–4.4 | Context & Scope |
| Beleid (het informatiebeveiligingsbeleid en de rest) | 5.2, 7.5 | Beleid & procedures |
| Rollen en verantwoordelijkheden | 5.3 | Gebruikers |
| Risicobeoordeling en -behandeling, criteria, acceptatie | 6.1.2–6.1.3 | SoA & Risico's |
| Verklaring van Toepasselijkheid | 6.1.3 d | SoA & Risico's → Statement of Applicability |
| Doelstellingen en meting | 6.2, 9.1 | KPI's |
| Bewustzijn, training en toetsen | 7.2–7.3 | Bewustzijn & training |
| Gedocumenteerde informatie, versies, bewijs | 7.5 | Beleid & procedures, Bewijs & audit trail |
| Operationele planning en beheersing | 8.1 | Taken |
| Assets en classificatie | 8.1, A.5.9–5.14 | Assets |
| Leveranciers en derdenrisico | A.5.19–5.23 | Leveranciers |
| Incidenten, afwijkingen, corrigerende maatregelen | 10.1–10.2, A.5.24–5.28 | Incidenten, Afwijkingen |
| Interne audit: programma, jaarplan, rondes, dekking | 9.2 | Audits |
| Directiebeoordeling: agenda, besluiten, verbeteracties | 9.3 | Management review |
| Wie wat wanneer wijzigde | — | Bewijs & audit trail → Audit trail |

Een visueel overzicht van het hele menu staat in
[Sitestructuur](/kennisbank/sitestructuur).

## Assets en incidenten: wat hier wel en niet in staat

Twee registers wekken gemakkelijk een verwachting die ze niet waarmaken. Dat is
opzet, en wie dat vooraf weet, bespaart zoekwerk.

**Het assetregister is geen assetmanagementsysteem.** Het register is geen CMDB en
geen inkoop- of licentieadministratie. Serienummers, aanschafwaarden en
contractdata ontbreken. Het register bevat wat de beoordeling draagt: of het
asset binnen de scope valt, wie eigenaar is en wie het beheert, de
BIV-classificatie met de datum waarop die is bepaald, of er persoonsgegevens in
zitten, en de uitgifte en teruggave per persoon. Systemen staan apart, met
hostingtype, leverancier, beschikbaarheidseis en redundantie.

Een sluitende inventaris van elk apparaat in de organisatie is hier dus niet te
verwachten. Het register hoort te bevatten wat binnen de ISMS-scope valt. De
operationele registratie staat meestal in een ander systeem.

**De incidentmodule is geen ticketsysteem.** De module heeft geen wachtrijen, geen
SLA-klok en geen meldportaal voor eindgebruikers. De module legt de
beveiligingskant vast: de ernst, het statusverloop, de koppeling aan een asset of
risico, de beoordeling van de externe meldplicht met termijnen, en de doorloop
naar afwijking, grondoorzaak, corrigerende maatregel en effectiviteitstoets.

Voor een steekproef betekent dat het volgende. De module bevat de voorvallen die
als beveiligingsincident zijn behandeld, en niet de volledige
servicedeskstroom. De bijbehorende auditvraag is daarom niet of alles hierin
staat, maar **hoe de organisatie bepaalt wat hier terechtkomt, en wie dat
beoordeelt**. Die afbakening hoort in het beleid te staan en niet in de software.

## De vier onderwerpen waar meestal het eerst naar wordt gevraagd

### 1. De Verklaring van Toepasselijkheid

Onder **SoA & Risico's → Statement of Applicability** staat elke maatregel uit de
bijlage. Per maatregel zijn vastgelegd: van toepassing ja/nee met motivatie, de
implementatiestatus, de datum van de laatste beoordeling, korte verwijzingen naar
het beleid en het proces dat de maatregel invult, en de gekoppelde
bewijsstukken. De lijst is te filteren op thema en status, en op de maatregelen
die nog niet beoordeeld zijn. Van dit scherm is een kopie te krijgen (zie
hieronder).

De organisatie legt de koppeling *risico → maatregel* vast bij de
risicobehandeling. Daar wordt aangevinkt welke maatregelen dat risico behandelen.
De keten risico → maatregel → SoA → bewijs is daardoor vanaf beide kanten te
volgen.

Naast de SoA staat een **restrisico-trend**. Per jaar is per maatregel het
restrisico met een toelichting vastgelegd. De ontwikkeling over de jaren is
daardoor zichtbaar en hoeft niet gereconstrueerd te worden.

### 2. Het risicoregister en het criteriakader

Onder **Risicoregister** staan de risico's met kans, impact, eigenaar, status en
behandeling. Het bijzondere deel zit in **Risicocriteria**. De schaal, de risk
appetite en de acceptatiedrempel vormen een vastgesteld kader met eigen versies.
De CISO stelt een versie op en de directie stelt die versie vast. De
beoordelingen verwijzen naar de versie die op dat moment gold.

Een restrisico boven de acceptatiedrempel kan niet stilzwijgend blijven staan.
Accepteren is een handeling die alleen de directie mag uitvoeren, en die
handeling wordt vastgelegd.

### 3. Incidenten en de afhandeling

Een incident kan pas worden gesloten als vier vragen beantwoord zijn. Is het
incident operationeel opgelost? Hangt er geen open afwijking meer aan? Is
vastgelegd waarom er geen corrigerende maatregel nodig was, of is er alsnog een
geopend? Is de externe meldplicht beoordeeld? Die meldplicht kent de termijnen van
de AVG en, als de organisatie eronder valt, die van de Cyberbeveiligingswet.

De afwijkingencyclus loopt door naar grondoorzaak, corrigerende maatregel en een
**effectiviteitstoets**. Die toets legt vast of de maatregel heeft gewerkt. De
toets is een apart record en geen vinkje.

### 4. De audit trail

De audit trail staat onder **Bewijs & audit trail → Audit trail**. Elke regel
bevat het tijdstip, de gebruiker, het gebied, de entiteit, de actie en de
gewijzigde velden met de oude en de nieuwe waarde. De regels zijn onder meer te
filteren op gebied en periode.

Drie eigenschappen zijn vooraf van belang:

- **Namen staan er als momentopname bij.** Een logregel toont de omschrijving
  zoals die op dat moment was, niet zoals die nu is. Als een account wordt
  verwijderd, blijft de naam in de oude regels staan.
- **De actie `status_gewijzigd` is geen goed filter voor alle statusovergangen.**
  Die actie wordt alleen gebruikt als de status het enige gewijzigde veld was. In
  de andere gevallen staat de overgang in de kolom met wijzigingen.
- **De regels vormen een keten.** Elke regel draagt de hash van zijn voorganger,
  een nachtelijke controle verifieert de keten, en de uitslagen blijven bewaard.
  Bovenaan het scherm staat tot welke regel de keten intact is en wanneer dat is
  gecontroleerd.

De volledige beschrijving, inclusief wat er bewust niet in de audit trail staat,
staat in [De audit trail](/kennisbank/de-audit-trail).

## Een kopie meenemen

Op het verzoek om een kopie levert het scherm een Word-document van precies wat
er op dat moment staat. Dat kan op vijf schermen: de Verklaring van
Toepasselijkheid, het risicoregister, de risicomatrix, de afwijkingen en de audit
trail.

Drie eigenschappen maken het document bruikbaar als auditbewijs:

- **Het document vermeldt zijn eigen omvang.** Bovenaan staat welke filters actief
  waren en hoeveel van het totale aantal regels erin staan, bijvoorbeeld *"36 van
  214"*. Een onvolledig overzicht dat zich als compleet presenteert, is het
  gevaarlijkste document in een dossier.
- **Elke pagina vermeldt zijn herkomst.** Onderaan staan de organisatie, de norm,
  het product met versienummer, de printdatum en het paginanummer van het totaal.
  Een los blad is daardoor altijd te herleiden. Als er **Ontwikkelversie** staat in
  plaats van *Productieversie*, komt het document niet uit de productieomgeving en
  is het geen bewijs van de werkende praktijk.
- **Wat is meegegeven, is geregistreerd.** Het systeem heeft een eigen register van
  schermkopieën. Achteraf is dus vast te stellen wat er is verstrekt.

De kopie van de audit trail draagt de kophash van de keten. Het is zinvol om die
kopie te bewaren. Bij een volgende audit is één vergelijking dan voldoende om vast
te stellen dat de historie niet is herschreven.

Als de installatie een tweede detailniveau heeft, komt dat als **bijlage** onder
de tabel mee, met een eigen omvangregel. In een BIO-installatie staan daar de
genummerde overheidsmaatregelen. Maatregel 5.24.03 staat dan als eigen regel in de
bijlage en is niet samengevat tot een cijfer bij 5.24. De normtekst zelf staat er
niet in. Die tekst is auteursrechtelijk beschermd en hoort in het exemplaar van de
norm dat de auditor zelf bij de hand heeft.

## Interne audits en dekking

Onder **Audits** staat de opbouw programma → jaarplan → ronde. Een ronde heeft een
scope (welke clausules en maatregelen), een auditor, een uitvoerdatum en
bevindingen. Bevindingen lopen door naar afwijkingen met corrigerende maatregelen.

De **dekkingsmatrix** toont per auditobject over de jaren van de cyclus of het
object gepland, uitgevoerd of niet gedekt is. Als "uitgevoerd" telt alleen een
afgeronde ronde met een uitvoerdatum die aan een jaarplan van dat programma hangt.
Afgevinkte vakjes tellen niet mee.

## Wat er van dit systeem niet te verwachten valt

De volgende punten staan hier kort op een rij, zodat er geen tijd aan verloren
gaat.

- **De normtekst staat er niet in.** Bij elke maatregel staan nummer, titel en
  thema, maar geen omschrijving. Dat is een keuze op grond van het auteursrecht.
  Als de organisatie de teksten zelf heeft ingevoerd, zijn ze er wel. Ze zijn dan
  overgenomen uit het eigen exemplaar van de norm van de organisatie.
- **Verwijzingen naar paragrafen zijn een hulpmiddel en geen bewijs.** Ze wijzen
  de weg naar de juiste plek in het eigen exemplaar. Ze garanderen niet dat de
  tekst daar zegt wat het scherm suggereert.
- **Er is geen compliance-score.** Het systeem doet geen uitspraak over de vraag
  of de organisatie voldoet. Dat oordeel is aan de auditor.
- **"Append-only" is in de applicatie hard, maar in de database niet vanzelf.** Er
  is geen scherm en geen knop om een logregel te wijzigen, maar iemand met
  databasetoegang kan de regels wel bereiken. De hardere controle bestaat uit een
  rechtenbeperking op databaseniveau en de ketencontrole die hierboven is
  beschreven. Een auditor mag daar terecht naar vragen.
- **Leesgedrag staat niet in de audit trail.** Wie een bewijsstuk heeft opgevraagd,
  staat in een aparte registratie met een eigen bewaartermijn. Het doel van die
  registratie is beperkt tot het onderbouwen van leesbevestigingen op beleid.

De volledige verantwoording, inclusief wat er uit openbare bronnen komt en waar
de grenzen liggen, staat in [Verantwoording en
disclaimer](/kennisbank/verantwoording-en-disclaimer).

## Welke norm deze installatie volgt

Bij de installatie is gekozen tussen ISO 27001, NEN 7510 en de BIO2. Die keuze
ligt daarna vast. Het geldende profiel staat bovenaan het menu: daar staat "ISMS"
met de naam van de norm erachter.

Bij alle drie normen hebben de hoofdstukken 4 tot en met 10 dezelfde Harmonized
Structure. Het verschil zit in de bijlage.

Bij **NEN 7510** bevat de bijlage de ISO-maatregelen plus acht zorgspecifieke
maatregelen. Die staan gewoon tussen de andere maatregelen in de Verklaring van
Toepasselijkheid. Als een bestaande maatregel een zorgspecifieke aanvulling heeft,
is dat als apart blok zichtbaar. Het blok bevat de tekst niet, maar meldt wel dat
er een aanvulling is. Zo is duidelijk waar de norm erbij nodig is.

Bij **BIO2** is de bijlage ongewijzigd en bevat die dezelfde maatregelen als onder
ISO. Het verschil zit een niveau lager. Onder een groot deel van de maatregelen
hangen genummerde *overheidsmaatregelen*. Die vormen de verplichte minimale
invulling en hebben elk een eigen status, onderbouwing en bewijs. Op dat niveau
hoort de auditor hier te toetsen. "A.5.24 is geïmplementeerd" is geen antwoord als
daar zeven genummerde verplichtingen onder hangen. Drie punten zijn daarbij van
belang:

- **Een uitzondering is smal.** Een overheidsmaatregel mag alleen
  niet-van-toepassing zijn als de maatregel niet van toepassing kán zijn, met een
  verwijzing naar de risicoanalyse. Die verwijzingen staan gebundeld in de bijlage
  "Uitzonderingen op de VvT" in de export. Waar een verwijzing ontbreekt, vermeldt
  de export dat expliciet.
- **Drie beheersmaatregelen vallen buiten de Cyberbeveiligingswet** (5.32, 5.33 en
  5.34: intellectueel eigendom, archivering, privacy). Daarvoor geldt verplichtende
  zelfregulering. De SoA markeert deze maatregelen.
- **Er is geen BIO-certificaat.** De norm verplicht geen certificering. In plaats
  daarvan legt de organisatie verantwoording af aan de RDI en stelt zij een
  jaarlijkse In Control Verklaring op.

## Nog één ding

Deze kennisbank is voor het auditoraccount toegankelijk en doorzoekbaar. Het
zoekveld boven de artikellijst springt naar de paragraaf waarin de gezochte term
staat. Als er een onbekend begrip opduikt, staat de uitleg er waarschijnlijk in.
