# De normteksten invoeren

Dit systeem levert de normtekst niet mee. Bij elke maatregel staat de officiële
titel, en op de plaats van de omschrijving staat een mededeling dat het systeem
die omschrijving niet levert. De reden daarvoor staat in [Verantwoording en
disclaimer](/kennisbank/verantwoording-en-disclaimer).

Een organisatie die de norm heeft gekocht, mag de teksten in de eigen installatie
zetten. Daarvoor is geen ontwikkelwerk nodig: het invoeren bestaat uit het
bewerken van één JSON-bestand met een editor en het draaien van één commando.
Deze pagina beschrijft die stappen precies.

> **De ingevoerde tekst mag deze installatie niet verlaten.** De licentie geldt
> voor de eigen organisatie. Dat vraagt aandacht bij de ISMS-export, bij
> schermkopieën voor een auditor en bij demonstraties. Zodra de tekst in het
> systeem staat, gaat die mee met alles wat uit het systeem wordt gehaald.

## Het aan te passen bestand

Het aan te passen bestand staat in de map van de applicatie, onder
`database/seeders/data/`. Welk bestand dat is, hangt af van de norm die deze
installatie volgt. Die norm staat boven het menu in de zijbalk:

| Norm | Bestand | Aantal maatregelen |
| --- | --- | --- |
| ISO/IEC 27001 | `maatregelen-iso27001.json` | 93 |
| NEN 7510 | `maatregelen-nen7510.json` | 101 |
| BIO2 | `maatregelen-bio2.json` | 93 |

In een installatie staat maar één van deze bestanden. De bestanden van de andere
normen worden bij de uitrol verwijderd, juist zodat niemand in het verkeerde
bestand werkt. Alle bestanden worden meegeleverd, dus er hoeft niets nieuw te
worden aangemaakt.

Bovenaan het bestand staat een blok `_over` met een korte uitleg van het bestand.
Daaronder staat `maatregelen`: een lijst van objecten. Per regel wordt de
`omschrijving` vervangen. De velden `annex_a_referentie`, `thema` en `naam`
blijven ongewijzigd. Die velden zijn gelijk aan de norm, en de koppeling met de
audits van de organisatie is ervan afhankelijk.

```json
{
  "annex_a_referentie": "5.1",
  "thema": "organisatorisch",
  "naam": "Beleidsregels voor informatiebeveiliging",
  "omschrijving": "Informatiebeveiligingsbeleid en onderwerpspecifieke …",
  "zorgaanvulling": "DO NOT TOUCH"
}
```

**Gedeeltelijk invullen is toegestaan.** Elke regel heeft een eigen markering.
Het is daardoor mogelijk om 10 maatregelen over te typen, het commando te draaien
en later verder te gaan. Maatregelen die nog niet zijn aangepast, blijven de
mededeling tonen.

**Het is verstandig om eerst een kopie van het bestand te maken**, buiten die
map. Met die kopie is de meegeleverde staat altijd terug te zetten.

In het BIO-profiel staan dezelfde 93 maatregelen in het bestand als onder ISO,
omdat de BIO Bijlage A ongewijzigd laat. Wat de BIO daaraan toevoegt, ligt een
niveau lager en heeft een eigen bestand en een eigen commando; zie *De
BIO-overheidsmaatregelen* onderaan deze pagina.

Het veld `zorgaanvulling` hoort bij NEN 7510 en niet bij de maatregeltekst. Dat
veld blijft ongewijzigd. Zie [Wat NEN 7510 toevoegt bovenop ISO
27001](/kennisbank/wat-nen-7510-toevoegt).

## Het commando

```
php artisan isms:maatregelen
```

Het commando is idempotent en wijzigt alleen de maatregelteksten.
SoA-beoordelingen, classificaties, koppelingen en bewijsstukken blijven behouden.
Het commando mag zo vaak worden gedraaid als nodig, bijvoorbeeld na elke 10
overgetypte maatregelen.

Het commando controleert eerst het hele bestand. Als er iets niet klopt, schrijft
het commando niets naar de database en meldt het wat er niet klopt. Daarna meldt
het commando hoeveel maatregelen een eigen normtekst hebben:

```
93 maatregelen bijgewerkt: 10 met een eigen normtekst, 83 met de meegeleverde mededeling.
```

Alleen controleren, zonder iets te schrijven, gaat met de optie `--controleer`:

```
php artisan isms:maatregelen --controleer
```

## Controleren

Na het invoeren toont een ingevoerde maatregel op **`/soa`** de eigen tekst. De
regel *"Dit ISMS levert bij deze maatregel geen omschrijving mee"* is dan
verdwenen.

## Als het niet werkt

**"Het bestand is geen geldige JSON."** Er is niets aan de database veranderd. De
oorzaak is bijna altijd een komma te veel achter de laatste regel, een ontbrekend
aanhalingsteken of een aanhalingsteken midden in een geplakte tekst. Een
aanhalingsteken midden in een tekst moet worden voorafgegaan door een backslash.
Het commando noemt de positie van de fout.

**"Dit profiel verwacht 93 maatregelen, het bestand heeft 92."** Er is een regel
verdwenen, meestal bij het plakken. Een vergelijking met de eerder gemaakte kopie
laat zien welke regel ontbreekt.

**"Veld 'omschrijving' ontbreekt of is leeg."** Een lege tekst is geen geldige
waarde. Voor een maatregel zonder eigen tekst blijft de meegeleverde mededeling
staan.

**Het te bewerken bestand bestaat niet.** In dat geval hoort het gezochte bestand
bij een andere norm. De tabel hierboven geeft aan welk bestand bij deze
installatie hoort.

**Na een aanpassing van `.env` verandert er niets.** Op een uitgerolde
installatie wordt de configuratie één keer ingelezen en bewaard. Een wijziging in
`.env` telt pas mee na het volgende commando:

```
php artisan config:cache
```

Een uitrol voert dit commando zelf uit, dus na een gewone uitrol is hiervoor geen
actie nodig. Voor het invoeren van normteksten maakt dit niets uit, omdat
`isms:maatregelen` niets uit `.env` leest.

## De BIO-overheidsmaatregelen

Dit onderdeel geldt alleen in het BIO-profiel en werkt iets anders dan hierboven
beschreven. De 118 overheidsmaatregelen staan in `overheidsmaatregelen-bio2.json`,
maar **hun tekst staat niet in dat bestand**. Dat bestand bevat alleen de
nummering, de koppeling aan de beheersmaatregel, de status en de reikwijdte van de
Cyberbeveiligingswet. De reden is een licentie en geen keuze: de BIO staat onder
CC BY-NC-SA 4.0. Zie [Verantwoording en
disclaimer](/kennisbank/verantwoording-en-disclaimer).

De teksten worden daarom niet in dat bestand ingevuld, maar in een tweede bestand
ernaast dat bij een bijwerking niet wordt overschreven:

```json
{
  "teksten": {
    "5.01.01": "De entiteit heeft een informatiebeveiligingsbeleid opgesteld…",
    "5.01.02": "…"
  }
}
```

Dat bestand heet `database/seeders/data/overheidsmaatregel-teksten.json`. Als de
BIO als spreadsheet beschikbaar is, maakt de meegeleverde generator dat bestand
aan:

```
python3 ../scripts/genereer_overheidsmaatregelen_seed.py --bron=<werkmap>.xlsx --met-tekst
```

Daarna volgt het inlezen, met dezelfde aanpak als hierboven: eerst controleren en
dan schrijven.

```
php artisan isms:overheidsmaatregelen --controleer
php artisan isms:overheidsmaatregelen
```

Het commando meldt achteraf hoeveel verplichtingen een eigen tekst hebben en
hoeveel er nog niet zijn beoordeeld. Gedeeltelijk invullen is toegestaan, net als
bij de maatregelteksten.

## De vijfde attribuutdimensie

Dit onderdeel staat los van het voorgaande en is optioneel. De dimensie
*capaciteiten* wordt evenmin meegeleverd, omdat zowel de waardenlijst als de
toewijzing per maatregel uit de norm komen. Een organisatie die de norm bezit,
maakt zelf `database/seeders/data/maatregel-capaciteiten.json` aan:

```json
{
  "vocabulaire": ["Governance", "Veilige configuratie", "…"],
  "regels": [ { "annex_a_referentie": "5.1", "capaciteiten": ["Governance"] } ]
}
```

Daarna volgt dit commando:

```
php artisan isms:capaciteiten aan
```

[Maatregelclassificatie](/kennisbank/maatregelclassificatie) beschrijft wat die
dimensie is en waarom die dimensie ontbreekt.
