# Wat de BIO toevoegt bovenop ISO 27001

De Baseline Informatiebeveiliging Overheid 2 (BIO2) is geen vervanging van
ISO 27001 en ook geen alternatief ervoor. De norm stelt dat zelf zonder
voorbehoud: *"De BIO2 vervangt deze twee normen niet, maar vult ze aan."*

Deze pagina legt uit wat die aanvulling inhoudt, waar die in dit systeem terug te
vinden is, en wat er anders is dan bij een ISO-installatie te verwachten valt.

## Twee delen die los van elkaar staan

De BIO2 bestaat uit twee delen die architectonisch weinig met elkaar te maken
hebben:

- **Deel 1, het BIO2-kader.** Dit deel is gestructureerd volgens
  NEN-EN-ISO/IEC 27001:2023. Het volgt dezelfde Harmonized Structure en heeft dus
  dezelfde hoofdstukken 4 tot en met 10. Het bevat geen maatregelen, maar eisen
  aan het managementsysteem, aan risicomanagement, aan de Verklaring van
  Toepasselijkheid, aan governance en aan de verantwoording.
- **Deel 2, de BIO-overheidsmaatregelen.** Dit deel is gestructureerd volgens
  NEN-EN-ISO/IEC 27002:2022 en vormt de maatregelentabel.

Een organisatie met een ISO 27001-managementsysteem voldoet al grotendeels aan
deel 1. De registers in dit systeem zijn dezelfde: issues, risico's, scope,
beleid, taken, bewijs, audits en review.

Het werkelijke verschil zit in deel 2.

## Geen extra maatregelen, maar een extra niveau

Dit punt wordt het vaakst verkeerd begrepen, ook door mensen die de norm kennen.

De BIO **voegt geen beheersmaatregelen toe**. Bijlage A bevat precies dezelfde 93
maatregelen als onder ISO 27001, met dezelfde nummers en dezelfde titels. Er is
niets bijgekomen en niets hernummerd.

De BIO voegt **een niveau onder die maatregelen** toe: 118 genummerde
*overheidsmaatregelen*, verdeeld over 54 van de 93 beheersmaatregelen. Onder
beheersmaatregel 5.24 hangen er zeven. Onder 5.20, 8.08 en 8.15 hangen er elk
zes. De andere 39 beheersmaatregelen hebben er geen.

De nummering bestaat uit drie delen en spreekt voor zich: **5.24.03** is
overheidsmaatregel 3 bij beheersmaatregel 5.24.

Over het karakter van die verplichtingen is de norm expliciet:

> *"Deze overheidsmaatregelen vormen de verplichte minimale invulling van de
> beheersmaatregel."*

en:

> *"Deze overheidsmaatregelen zijn altijd verplicht en kunnen ongeacht de
> risico-inschatting van de entiteit niet geaccepteerd worden, tenzij ze niet van
> toepassing kunnen zijn."*

De uitzondering na "tenzij" is smal. Onder ISO 27001 is het niet van toepassing
verklaren van een maatregel een vrije uitspraak met een motivatie. Onder de BIO
mag dat alleen als de maatregel niet van toepassing kan zijn. De onderbouwing
hoort dan, met een verwijzing naar de risicoanalyse, in een bijlage
"Uitzonderingen" bij de VvT.

**De plaats in dit systeem:** op de pagina *Statement of Applicability* staat per
beheersmaatregel een kolom **Verplichtingen** met de dekking, bijvoorbeeld
`3 / 7`. Als er uitzonderingen zijn, staat het aantal daarvan erachter. Een klik
op dat cijfer klapt de verplichtingen onder de regel open, met nummer, status,
tekst, onderbouwing, verwijzingen en het bewijs dat eronder hangt. Dat is een
leesweergave. Het beoordelen gebeurt met de knop *Beoordelen*, in het blok
"Overheidsmaatregelen (BIO2)" onder de omschrijving. Een streepje in de kolom
betekent dat de beheersmaatregel geen overheidsmaatregelen heeft. Voor die
beheersmaatregelen geldt een andere route, die de volgende paragraaf beschrijft.

Een **kopie voor de auditor** bevat de verplichtingen als eigen regels in een
bijlage onder de SoA-tabel, ongeacht welke regels in het scherm waren
opengeklapt.

## De beheersmaatregelen zonder overheidsmaatregel

Voor de 39 beheersmaatregelen zonder overheidsmaatregel geldt een eigen route.
Die route staat in de inleiding van deel 2:

> *"Als een dergelijke beheersmaatregel van toepassing is, wordt gebruik gemaakt
> van de bijbehorende implementatierichtlijn uit NEN-EN-ISO/IEC 27002. Afwijken of
> niet toepassen van de bovenliggende beheersmaatregel wordt onderbouwd met een
> risicoanalyse. De referentie naar deze analyse is in een bijlage uitzonderingen
> opgenomen in de Verklaring van Toepasselijkheid (VvT)."*

Als bij zo'n maatregel het blok met overheidsmaatregelen ontbreekt, is dat dus
geen gat in dit systeem. De norm schrijft daar niets voor, en dan geldt ISO 27002
als terugvaloptie.

## Wat niet onder de Cyberbeveiligingswet valt

Sinds uitgave v1.3 markeert de BIO welke maatregelen buiten de reikwijdte van de
Cyberbeveiligingswet vallen:

> *"Grijs gemarkeerde overheidsmaatregelen met bijbehorende beheersmaatregelen
> vallen niet onder de reikwijdte van de Cbw. Hiervoor geldt verplichtende
> zelfregulering."*

Het gaat om drie beheersmaatregelen. De logica is goed te volgen, want het zijn de
onderwerpen waarvoor al een eigen wet bestaat:

| Beheersmaatregel | Onderwerp | Eigen kader |
|---|---|---|
| 5.32 | Intellectuele-eigendomsrechten | Auteurswet e.a. |
| 5.33 | Bescherming van registraties | Archiefwet |
| 5.34 | Privacy en bescherming van PII | AVG |

Dit systeem markeert die drie in de SoA. Het verschil is relevant. Bij de andere
90 is de grondslag een wettelijke plicht die de RDI kan handhaven. Bij deze drie
is de grondslag een bestuurlijke afspraak.

**Dit onderscheid staat los van de situatie van de eigen organisatie.** Of een
organisatie onder de Cyberbeveiligingswet valt, hangt af van sector en omvang en
staat los van de norm die de organisatie volgt. Dat is in dit systeem een aparte
instelling. Deel 1 §11.1 noemt expliciet de BIO-entiteit die buiten de Cbw valt.
Voor zo'n entiteit geldt de hele BIO als verplichtende zelfregulering.

## Verantwoording in plaats van certificering

Onder ISO 27001 werkt een organisatie naar een certificaat toe. Onder de BIO is
dat niet zo: *"De BIO verplicht geen NEN-EN-ISO/IEC 27001-certificering."*

Verantwoording komt daarvoor in de plaats. Overheidsmaatregel 5.36.01 en deel 1
§9 vragen om een jaarlijkse **In Control Verklaring**. Het toezicht loopt via de
Cyberbeveiligingswet, met de **RDI** als toezichthouder voor de sector Overheid.

Daardoor verschuift het zwaartepunt van dit systeem. Het zwaartepunt ligt niet in
het opleveren van een dossier voor een certificerende instelling, maar in het
gedurende het hele jaar kunnen aantonen dat de verplichtingen belegd zijn, met
bewijs en met een datum. Om die reden houdt de beoordeling per overheidsmaatregel
bij wanneer er voor het laatst naar gekeken is. De status "belegd" zonder datum
verliest na verloop van tijd zijn waarde.

## Wat BIO2 heeft laten vallen

Voor organisaties die van de vorige generatie (BIO 1.04) komen, zijn twee
onderdelen verdwenen:

- **Het basisbeveiligingsniveau (BBN 1, 2, 3).** BIO2 kent dit niveau niet meer.
  In de officiële wordt-was-lijst staat de BBN-kolom voor BIO2 over de hele linie
  op "niet van toepassing".
- **De vaste verantwoordelijke per maatregel.** BIO1 wees per maatregel een rol
  aan (secretaris/algemeen directeur, proceseigenaar, dienstenleverancier). BIO2
  doet dat niet meer.

Dit systeem heeft die velden daarom niet. Dat is een keuze van de norm en geen
omissie in het systeem. Een verantwoordelijke voor een verplichting wordt in dit
ISMS vastgelegd via een taak, omdat een taak een eigenaar en een deadline heeft.

Daarnaast is de inhoud sterk verschoven. Van de 118 overheidsmaatregelen zijn er
87 één-op-één uit BIO1 overgenomen, 6 zijn samengevoegd uit meerdere
BIO1-maatregelen, en **25 zijn nieuw**. Omgekeerd hebben 25 BIO1-maatregelen geen
opvolger in BIO2. Een BIO1-beoordeling laat zich daarom niet zonder meer
overzetten.

## Twee andere BIO-varianten

Het CIP onderhoudt op dit moment drie normatieve varianten naast elkaar. Dit
systeem volgt er één, **BIO2**. Het is nuttig te weten welke twee varianten het
systeem niet volgt:

- **Handreiking BIO2-opmaat.** Deze variant is bedoeld voor organisaties die zich
  op BIO2 voorbereiden, maar (nog) niet onder de Cbw vallen. De variant heeft
  dezelfde 93 beheersmaatregelen, maar 149 overheidsmaatregelen in plaats van
  118. De nummering is anders (`5.01.1` in plaats van `5.01.01`) en de
  formuleringen zijn eigen: "de organisatie" waar BIO2 "de entiteit" schrijft.
- **BIO1 v1.04zv.** Dit is de vorige generatie, gebouwd op de indeling van
  ISO 27002:**2013** met 116 controls. Die nummering past niet op Bijlage A zoals
  die sinds 2022 luidt. In feite is dit dus een andere maatregelenset.

## Samengevat

| | ISO 27001 | BIO2 |
|---|---|---|
| Beheersmaatregelen | 93 | dezelfde 93 |
| Niveau daaronder | — | 118 overheidsmaatregelen |
| Niet van toepassing verklaren | vrij, met motivatie | alleen als het niet van toepassing kan zijn |
| Doel | certificering | verantwoording aan de RDI |
| Toezicht | certificerende instelling | RDI, onder de Cyberbeveiligingswet |
| Jaarlijkse uitkomst | auditrapport | In Control Verklaring |
| Basisbeveiligingsniveau | — | niet meer in BIO2 |

Kort samengevat: **de BIO maakt Bijlage A concreter, niet groter.** Waar ISO
voorschrijft dat toegangsrechten beheerd worden, schrijft de BIO voor hoe vaak ze
beoordeeld worden.
