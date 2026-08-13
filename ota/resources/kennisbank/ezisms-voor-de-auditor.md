# EzISMS voor de externe auditor: een rondleiding

Een auditor ziet veel ISMS-en; dit is er waarschijnlijk geen bekende van. Deze
pagina is bedoeld als kwartiertje inwerken: hoe het geheel is ingedeeld, waar
elke clausule terechtkomt, hoe het bewijs is vastgelegd, en wat het systeem
bewust *niet* doet. Technische kennis is niet nodig.

## Het model in vier regels

1. **Eén organisatie per installatie.** Wat er te zien is, is het hele ISMS; er is
   geen andere omgeving en geen tweede administratie.
2. **Alles is een register.** Elk onderwerp — risico's, maatregelen, beleid,
   incidenten, leveranciers, audits — is een lijst met records die naar elkaar
   verwijzen.
3. **Bewijs hangt aan het record**, niet in een aparte map. Eén bewijsstuk kan aan
   meerdere records gekoppeld zijn.
4. **Elke wijziging komt in de audit trail**: wie, wat, wanneer, op welk gebied,
   met de oude en de nieuwe waarde.

## Het auditoraccount

Een auditor krijgt een account met de rol **Auditor**. Dat betekent:

- **Leesrecht op vrijwel alles**, inclusief de audit trail en de bewijsstukken.
- **Geen enkel muteerrecht.** Er valt niets aan te passen, ook niet per ongeluk.
  Moet er iets zichtbaar worden wat achter een handeling zit, dan voert de CISO
  die handeling uit terwijl de auditor meekijkt — en de handeling komt gewoon in
  de trail.
- **Eén uitzondering op "leest alles":** een interne auditor die aan een
  auditronde is toegewezen, mag op díé ronde bevindingen vastleggen. Bij een
  externe certificeringsaudit is dat niet de bedoeling: daar blijft het
  auditrapport de bron van waarheid en neemt de CISO de bevindingen over.

Links in het scherm staat het menu. Wat daarin verschijnt volgt de rechten van
het account, dus items zonder inzage blijven weg.

## Waar wat te vinden is

De navigatie volgt de opbouw van het managementsysteem, niet de nummering van de
norm. Deze tabel is de vertaling.

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

## Assets en incidenten: wat hier wél en niet in staat

Twee registers wekken makkelijk een verwachting die ze niet waarmaken, en dat is
opzet — dat vooraf weten scheelt zoekwerk.

**Het assetregister is geen assetmanagementsysteem.** Het is geen CMDB en geen
inkoop- of licentieadministratie: serienummers, aanschafwaarden en contractdata
ontbreken. Wat er wél staat is wat de beoordeling draagt — of het asset binnen de
scope valt, wie eigenaar is en wie het beheert, de BIV-classificatie met de datum
waarop die is bepaald, of er persoonsgegevens in zitten, en de uitgifte en
teruggave per persoon. Systemen staan apart, met hostingtype, leverancier,
beschikbaarheidseis en redundantie.

Een sluitende inventaris van elk apparaat in de organisatie is hier dus niet te
verwachten. Wat hier hoort te staan, is wat binnen de ISMS-scope valt; de
operationele registratie leeft meestal in een ander systeem.

**De incidentmodule is geen ticketsysteem.** Geen wachtrijen, geen SLA-klok, geen
meldportaal voor eindgebruikers. Vastgelegd is de beveiligingskant: ernst, het
statusverloop, de koppeling aan een asset of risico, de beoordeling van de
externe meldplicht met termijnen, en de doorloop naar afwijking, grondoorzaak,
corrigerende maatregel en effectiviteitstoets.

Voor een steekproef betekent dat: hier staan de voorvallen die als
beveiligingsincident zijn behandeld, niet de volledige servicedeskstroom. De
vraag die daarbij hoort is dan ook niet "staat alles hierin", maar **hoe de
organisatie bepaalt wat hier terechtkomt, en wie dat beoordeelt**. Die afbakening
hoort in het beleid te staan, niet in de software.

## De vier onderwerpen waar meestal het eerst naar wordt gevraagd

### 1. De Verklaring van Toepasselijkheid

Onder **SoA & Risico's → Statement of Applicability** staat elke maatregel uit de
bijlage met: van toepassing ja/nee met motivatie, de implementatiestatus, de
datum van de laatste beoordeling, korte verwijzingen naar het beleid en het
proces dat hem invult, en de eraan gekoppelde bewijsstukken. Filteren kan op
thema en status, en op de maatregelen die nog niet beoordeeld zijn. Van dit
scherm is een kopie mee te krijgen (zie hieronder).

De koppeling *risico → maatregel* legt de organisatie vast bij de
risicobehandeling: daar wordt aangevinkt welke maatregelen dat risico behandelen.
De keten risico → maatregel → SoA → bewijs is daardoor vanaf beide kanten te
volgen.

Naast de SoA staat een **restrisico-trend**: per jaar is per maatregel het
restrisico vastgelegd met toelichting, zodat beweging over de jaren zichtbaar is
en niet gereconstrueerd hoeft te worden.

### 2. Het risicoregister en het criteriakader

Onder **Risicoregister** staan de risico's met kans, impact, eigenaar, status en
behandeling. Het bijzondere zit in **Risicocriteria**: de schaal, de risk appetite
en de acceptatiedrempel zijn een *vastgesteld* kader met eigen versies. De CISO
stelt een versie op, de directie stelt hem vast — en de beoordelingen verwijzen
naar de versie die op dat moment gold.

Een restrisico boven de acceptatiedrempel kan niet stilzwijgend blijven staan:
accepteren is een handeling die alleen de directie mag doen, en die is vastgelegd.

### 3. Incidenten en de afhandeling

Een incident kan niet worden gesloten voordat vier vragen beantwoord zijn: is het
operationeel opgelost, hangt er geen open afwijking meer aan, is vastgelegd
waarom er géén corrigerende maatregel nodig was (of is er alsnog een geopend), en
is de externe meldplicht beoordeeld. Dat laatste kent de termijnen van de AVG en
— als de organisatie eronder valt — de Cyberbeveiligingswet.

De afwijkingencyclus loopt door naar grondoorzaak, corrigerende maatregel en een
**effectiviteitstoets**: heeft de maatregel gewerkt? Dat is een apart record, geen
vinkje.

### 4. De audit trail

Onder **Bewijs & audit trail → Audit trail**. Per regel: tijdstip, gebruiker,
gebied, entiteit, actie en de gewijzigde velden met oud en nieuw. Filteren kan
onder meer op gebied en periode.

Drie dingen om vooraf te weten:

- **Namen staan er als momentopname bij.** Een logregel toont de omschrijving
  zoals die tóén was, niet zoals die nu is. Wordt een account verwijderd, dan
  blijft de naam in de oude regels staan.
- **De actie `status_gewijzigd` is geen goed filter voor alle statusovergangen.**
  Die actie wordt alleen gebruikt als de status het énige gewijzigde veld was;
  anders zit de overgang in de kolom met wijzigingen.
- **De regels vormen een keten.** Elke regel draagt de hash van zijn voorganger,
  een nachtelijke controle verifieert de keten, en de uitslagen blijven bewaard.
  Bovenaan het scherm staat tot welke regel de keten intact is en wanneer dat is
  gecontroleerd.

Het volledige verhaal, inclusief wat er bewust *niet* in de trail staat, staat in
[De audit trail](/kennisbank/de-audit-trail).

## Een kopie meenemen

Op de vraag "mag ik hier een kopie van?" levert het scherm een Word-document van
precies wat er op dat moment staat. Dat kan op vijf schermen: de Verklaring van
Toepasselijkheid, het risicoregister, de risicomatrix, de afwijkingen en de audit
trail.

Drie eigenschappen maken het document bruikbaar als auditbewijs:

- **Het vertelt zijn eigen omvang.** Bovenaan staat welke filters actief waren en
  hoeveel van hoeveel regels erin staan — *"36 van 214"*. Een onvolledig overzicht
  dat zich als compleet presenteert, is het gevaarlijkste document in een dossier.
- **Elke pagina draagt zijn herkomst.** Onderaan staan de organisatie, de norm,
  het product met versienummer, de printdatum en het hoeveelste blad van hoeveel.
  Een los blad blijft daardoor thuis te brengen. Staat er **Ontwikkelversie** in
  plaats van *Productieversie*, dan komt het document niet uit de
  productieomgeving en is het geen bewijs van de werkende praktijk.
- **Wat is meegegeven, is geregistreerd.** Er is een eigen register van
  schermkopieën, dus achteraf is vast te stellen wat er is verstrekt.

De kopie van de audit trail draagt de kophash van de keten. Die kopie is de
moeite van het bewaren waard: bij een volgende audit is één vergelijking genoeg
om vast te stellen dat de historie niet is herschreven.

## Interne audits en dekking

Onder **Audits** staat de opbouw programma → jaarplan → ronde. Een ronde heeft een
scope (welke clausules en maatregelen), een auditor, een uitvoerdatum en
bevindingen; bevindingen lopen door naar afwijkingen met corrigerende maatregelen.

De **dekkingsmatrix** laat per auditobject over de jaren van de cyclus zien of het
gepland, uitgevoerd of niet gedekt is. "Uitgevoerd" telt alleen een afgeronde
ronde met uitvoerdatum die aan een jaarplan van dat programma hangt — geen
afgevinkte vakjes.

## Wat er van dit systeem níét te verwachten valt

Kort en eerlijk, zodat er geen tijd aan verloren gaat.

- **De normtekst staat er niet in.** Bij elke maatregel staan nummer, titel en
  thema, en geen omschrijving. Dat is een auteursrechtelijke keuze. Heeft de
  organisatie de teksten zelf ingevoerd, dan zijn ze er wel — overgenomen uit haar
  eigen exemplaar van de norm.
- **Verwijzingen naar paragrafen zijn een hulpmiddel, geen bewijs.** Ze wijzen de
  weg naar de juiste plek in het eigen exemplaar; ze garanderen niet dat de tekst
  daar zegt wat het scherm suggereert.
- **Er is geen compliance-score.** Het systeem doet geen uitspraak over de vraag
  of de organisatie voldoet. Dat oordeel is aan de auditor.
- **"Append-only" is in de applicatie hard, in de database niet vanzelf.** Er is
  geen scherm en geen knop om een logregel te wijzigen, maar wie databasetoegang
  heeft, komt er wel bij. De hardere controle is een rechtenbeperking op
  databaseniveau plus de ketencontrole hierboven — daarnaar vragen is terecht.
- **Leesgedrag zit niet in de trail.** Wie een bewijsstuk opvroeg staat in een
  aparte registratie met een eigen bewaartermijn; het doel daarvan is beperkt tot
  het onderbouwen van leesbevestigingen op beleid.

De volledige verantwoording, inclusief wat er uit openbare bronnen komt en waar
de grenzen liggen, staat in [Verantwoording en
disclaimer](/kennisbank/verantwoording-en-disclaimer).

## Welke norm deze installatie volgt

Bij installatie is gekozen tussen ISO 27001 en NEN 7510, en die keuze ligt daarna
vast. Welk profiel geldt, staat bovenaan het menu: daar staat "ISMS" met de naam
van de norm erachter.

Is dat NEN 7510, dan verandert er minder dan het lijkt. De hoofdstukken 4 tot en
met 10 zijn dezelfde Harmonized Structure; de bijlage bevat de ISO-maatregelen
plus acht zorgspecifieke, die gewoon tussen de andere in de Verklaring van
Toepasselijkheid staan. Waar een bestaande maatregel een zorgspecifieke aanvulling
heeft, is dat als apart blok zichtbaar — zonder de tekst, maar mét de mededeling
dát er een is, zodat duidelijk is waar de norm erbij nodig is.

## Nog één ding

Deze kennisbank is voor het auditoraccount toegankelijk en doorzoekbaar; het
zoekveld boven de artikellijst springt naar de paragraaf waarin de gezochte term
staat. Duikt er een begrip op dat hier niet bekend is, dan staat de uitleg er
waarschijnlijk in.
