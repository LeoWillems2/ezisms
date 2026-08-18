# Wat de BIO toevoegt bovenop ISO 27001

De Baseline Informatiebeveiliging Overheid 2 (BIO2) is geen vervanging van
ISO 27001 en ook geen alternatief ervoor. De norm zegt het zelf onomwonden:
*"De BIO2 vervangt deze twee normen niet, maar vult ze aan."*

Deze pagina legt uit wat die aanvulling precies is, waar je die in dit systeem
terugvindt, en wat er ánders is dan je van een ISO-installatie zou verwachten.

## Twee delen die los van elkaar staan

De BIO2 bestaat uit twee delen die architectonisch weinig met elkaar te maken
hebben:

- **Deel 1, het BIO2-kader.** Gestructureerd volgens NEN-EN-ISO/IEC 27001:2023 —
  dezelfde Harmonized Structure, dus dezelfde hoofdstukken 4 tot en met 10. Geen
  maatregelen, maar eisen aan het managementsysteem, aan risicomanagement, aan de
  Verklaring van Toepasselijkheid, aan governance en aan de verantwoording.
- **Deel 2, de BIO-overheidsmaatregelen.** Gestructureerd volgens
  NEN-EN-ISO/IEC 27002:2022, en dít is de maatregelentabel.

Voor deel 1 geldt: als je een ISO 27001-managementsysteem hebt, heb je het meeste
al. De registers in dit systeem — issues, risico's, scope, beleid, taken, bewijs,
audits, review — zijn dezelfde.

Deel 2 is waar het echte verschil zit.

## Geen extra maatregelen, maar een extra niveau

Dit is het punt dat het makkelijkst wordt misbegrepen, ook door mensen die de norm
kennen.

De BIO **voegt geen beheersmaatregelen toe**. Bijlage A houdt precies dezelfde 93
maatregelen als onder ISO 27001, met dezelfde nummers en dezelfde titels. Er is
niets bijgekomen en niets hernummerd.

Wat de BIO doet, is er een **niveau onder hangen**: 118 genummerde
*overheidsmaatregelen*, verdeeld over 54 van die 93. Onder beheersmaatregel 5.24
hangen er zeven; onder 5.20, 8.08 en 8.15 elk zes. De andere 39
beheersmaatregelen hebben er geen.

De nummering is drieledig en leest zichzelf: **5.24.03** is overheidsmaatregel 3
bij beheersmaatregel 5.24.

Over het karakter van die verplichtingen is de norm expliciet:

> *"Deze overheidsmaatregelen vormen de verplichte minimale invulling van de
> beheersmaatregel."*

en:

> *"Deze overheidsmaatregelen zijn altijd verplicht en kunnen ongeacht de
> risico-inschatting van de entiteit niet geaccepteerd worden, tenzij ze niet van
> toepassing kunnen zijn."*

Dat "tenzij" is smal. Onder ISO 27001 is een maatregel niet van toepassing
verklaren een vrije uitspraak met een motivatie. Onder de BIO mag dat alleen als de
maatregel *niet van toepassing kán zijn*, en de onderbouwing hoort met een
verwijzing naar de risicoanalyse in een bijlage "Uitzonderingen" bij de VvT.

**Waar je dat in dit systeem terugvindt:** op de pagina *Statement of
Applicability* staat per beheersmaatregel een kolom **Verplichtingen** met de
dekking — bijvoorbeeld `3 / 7`, en het aantal uitzonderingen erachter als die er
zijn. Klik op dat cijfer en de verplichtingen klappen onder de regel open: nummer,
status, tekst, onderbouwing, verwijzingen en het bewijs dat eronder hangt. Dat is
een leesweergave; beoordelen doe je met de knop *Beoordelen*, in het blok
"Overheidsmaatregelen (BIO2)" onder de omschrijving. Een streepje in de kolom
betekent dat deze beheersmaatregel er geen heeft — zie de volgende paragraaf, want
daar geldt een andere route.

Neem je een **kopie voor de auditor** mee, dan staan de verplichtingen daar als
eigen regels in een bijlage onder de SoA-tabel, ongeacht wat je had opengeklapt.

## De beheersmaatregelen zonder overheidsmaatregel

Voor de 39 beheersmaatregelen zonder overheidsmaatregel geldt een eigen route, en
die staat in de inleiding van deel 2:

> *"Als een dergelijke beheersmaatregel van toepassing is, wordt gebruik gemaakt
> van de bijbehorende implementatierichtlijn uit NEN-EN-ISO/IEC 27002. Afwijken of
> niet toepassen van de bovenliggende beheersmaatregel wordt onderbouwd met een
> risicoanalyse. De referentie naar deze analyse is in een bijlage uitzonderingen
> opgenomen in de Verklaring van Toepasselijkheid (VvT)."*

Ontbreekt bij zo'n maatregel het blok met overheidsmaatregelen, dan is dat dus geen
gat in dit systeem maar de norm die daar niets voorschrijft — en dan val je terug
op ISO 27002.

## Wat níét onder de Cyberbeveiligingswet valt

Sinds uitgave v1.3 markeert de BIO welke maatregelen buiten de reikwijdte van de
Cyberbeveiligingswet vallen:

> *"Grijs gemarkeerde overheidsmaatregelen met bijbehorende beheersmaatregelen
> vallen niet onder de reikwijdte van de Cbw. Hiervoor geldt verplichtende
> zelfregulering."*

Het zijn er drie, en de logica erachter is goed te volgen — het zijn de
onderwerpen die hun eigen wet al hebben:

| Beheersmaatregel | Onderwerp | Eigen kader |
|---|---|---|
| 5.32 | Intellectuele-eigendomsrechten | Auteurswet e.a. |
| 5.33 | Bescherming van registraties | Archiefwet |
| 5.34 | Privacy en bescherming van PII | AVG |

Dit systeem markeert die drie in de SoA. Het verschil doet ertoe: bij de andere 90
is de grondslag een wettelijke plicht die de RDI kan handhaven, hier is het een
bestuurlijke afspraak.

**Let op het onderscheid met je eigen situatie.** Of jóuw organisatie onder de
Cyberbeveiligingswet valt, hangt af van sector en omvang en staat los van welke
norm je volgt. Dat is een aparte instelling in dit systeem. Deel 1 §11.1 noemt de
BIO-entiteit die buiten de Cbw valt expliciet; dan geldt de hele BIO als
verplichtende zelfregulering.

## Verantwoording in plaats van certificering

Onder ISO 27001 werk je naar een certificaat. Onder de BIO niet: *"De BIO
verplicht geen NEN-EN-ISO/IEC 27001-certificering."*

Wat ervoor in de plaats komt is verantwoording. Overheidsmaatregel 5.36.01 en deel
1 §9 vragen om een jaarlijkse **In Control Verklaring**, en het toezicht loopt via
de Cyberbeveiligingswet met de **RDI** als toezichthouder voor de sector Overheid.

Dat verschuift waar het zwaartepunt van dit systeem ligt. Niet in het opleveren
van een dossier voor een certificerende instelling, maar in het jaar rond kunnen
aantonen dat de verplichtingen belegd zijn, met bewijs en met een datum. Vandaar
dat de beoordeling per overheidsmaatregel bijhoudt *wanneer* er voor het laatst
naar gekeken is: "belegd" zonder datum verjaart.

## Wat BIO2 heeft laten vallen

Kom je van de vorige generatie (BIO 1.04), dan zijn twee dingen verdwenen die je
misschien zoekt:

- **Het basisbeveiligingsniveau (BBN 1, 2, 3).** BIO2 kent het niet meer. In de
  officiële wordt-was-lijst staat de BBN-kolom voor BIO2 over de hele linie op
  "niet van toepassing".
- **De vaste verantwoordelijke per maatregel.** BIO1 wees per maatregel een rol
  aan (secretaris/algemeen directeur, proceseigenaar, dienstenleverancier). BIO2
  doet dat niet meer.

Dit systeem heeft die velden daarom niet. Dat is een keuze van de norm, geen
omissie hier. Wie in dit ISMS een verantwoordelijke aan een verplichting wil
hangen, doet dat via een taak — die heeft een eigenaar en een deadline.

Verder is de inhoud flink verschoven: van de 118 overheidsmaatregelen zijn er 87
één-op-één uit BIO1 overgenomen, 6 samengevoegd uit meerdere BIO1-maatregelen, en
**25 zijn nieuw**. Andersom hebben 25 BIO1-maatregelen geen opvolger in BIO2. Een
BIO1-beoordeling laat zich dus niet zomaar overzetten.

## Twee andere BIO-varianten die je kunt tegenkomen

Het CIP onderhoudt op dit moment drie normatieve varianten naast elkaar. Dit
systeem volgt er één — **BIO2** — en het is nuttig te weten welke twee dat niet
zijn:

- **Handreiking BIO2-opmaat.** Voor organisaties die zich op BIO2 voorbereiden
  maar (nog) niet onder de Cbw vallen. Zelfde 93 beheersmaatregelen, maar 149
  overheidsmaatregelen in plaats van 118, met een eigen nummering (`5.01.1` in
  plaats van `5.01.01`) en eigen formuleringen — "de organisatie" waar BIO2 "de
  entiteit" zegt.
- **BIO1 v1.04zv.** De vorige generatie, gebouwd op de indeling van
  ISO 27002:**2013** met 116 controls. Die nummering past niet op Bijlage A zoals
  die sinds 2022 is, dus dit is in feite een andere maatregelenset.

## Samengevat

| | ISO 27001 | BIO2 |
|---|---|---|
| Beheersmaatregelen | 93 | dezelfde 93 |
| Niveau daaronder | — | 118 overheidsmaatregelen |
| Niet van toepassing verklaren | vrij, met motivatie | alleen als het niet van toepassing *kán* zijn |
| Doel | certificering | verantwoording aan de RDI |
| Toezicht | certificerende instelling | RDI, onder de Cyberbeveiligingswet |
| Jaarlijkse uitkomst | auditrapport | In Control Verklaring |
| Basisbeveiligingsniveau | — | niet meer in BIO2 |

De korte versie: **de BIO maakt Bijlage A concreter, niet groter.** Waar ISO
zegt "beheer je toegangsrechten", zegt de BIO hoe vaak je ze beoordeelt.
