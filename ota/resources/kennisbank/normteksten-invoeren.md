# De normteksten invoeren

Dit systeem levert de normtekst niet mee. Bij elke maatregel staat de officiële
titel, en op de plaats van de omschrijving een mededeling dat wij die niet
leveren. Waarom dat zo is, staat in [Verantwoording en
disclaimer](/kennisbank/verantwoording-en-disclaimer).

Heeft u de norm gekocht, dan mag u de teksten in uw eigen installatie zetten. Dat
is geen ontwikkelwerk: u bewerkt één JSON-bestand met een editor en draait één
commando. Deze pagina beschrijft precies hoe.

> **De ingevoerde tekst mag deze installatie niet verlaten.** Uw licentie geldt
> voor uw organisatie. Let daarop bij de ISMS-export, bij schermkopieën voor een
> auditor en bij demonstraties: zodra de tekst in het systeem staat, reist hij
> mee met alles wat u eruit haalt.

## Wat u aanpast

Eén bestand in de map van de applicatie, onder
`database/seeders/data/`. Welk bestand hangt af van de norm die deze installatie
volgt — die staat boven het menu in de zijbalk:

| Norm | Bestand | Aantal maatregelen |
| --- | --- | --- |
| ISO/IEC 27001 | `maatregelen-iso27001.json` | 93 |
| NEN 7510 | `maatregelen-nen7510.json` | 101 |

Er staat er maar één in uw installatie; de andere wordt bij de uitrol
weggehaald, juist zodat u niet in het verkeerde bestand zit te werken. Beide
worden meegeleverd, dus u maakt niets nieuw aan.

Bovenin staat een blok `_over` dat het bestand kort uitlegt. Daaronder staat
`maatregelen`: een lijst objecten. Vervang per regel de `omschrijving`; laat
`annex_a_referentie`, `thema` en `naam` staan zoals ze zijn — die zijn gelijk aan
de norm en de koppeling met uw audits hangt eraan.

```json
{
  "annex_a_referentie": "5.1",
  "thema": "organisatorisch",
  "naam": "Beleidsregels voor informatiebeveiliging",
  "omschrijving": "Informatiebeveiligingsbeleid en onderwerpspecifieke …",
  "zorgaanvulling": "DO NOT TOUCH"
}
```

**Gedeeltelijk invullen mag.** Elke regel draagt zijn eigen markering, dus u kunt
tien maatregelen overtypen, het commando draaien, en later verdergaan. Wat u nog
niet hebt aangeraakt blijft de mededeling tonen.

**Maak eerst een kopie** van het bestand buiten die map. Dat is uw weg terug naar
de meegeleverde staat.

Het veld `zorgaanvulling` gaat over NEN 7510 en niet over de maatregeltekst; laat
het staan zoals het is. Zie [Wat NEN 7510 toevoegt bovenop ISO
27001](/kennisbank/wat-nen-7510-toevoegt).

## Het commando

```
php artisan isms:maatregelen
```

Dit is idempotent en raakt alleen de maatregelteksten aan: uw SoA-beoordelingen,
classificaties, koppelingen en bewijsstukken blijven staan. U mag het zo vaak
draaien als u wilt — bijvoorbeeld na elke tien maatregelen die u hebt overgetypt.

Het controleert het bestand eerst helemaal. Klopt er iets niet, dan gaat er niets
naar de database en hoort u wát er niet klopt. Daarna leest u hoeveel maatregelen
er een eigen normtekst hebben:

```
93 maatregelen bijgewerkt: 10 met een eigen normtekst, 83 met de meegeleverde mededeling.
```

Wilt u alleen controleren zonder iets te schrijven:

```
php artisan isms:maatregelen --controleer
```

## Controleren

Open op **`/soa`** een maatregel die u hebt ingevoerd. U ziet uw eigen tekst, en
de regel *"Dit ISMS levert bij deze maatregel geen omschrijving mee"* is
verdwenen.

## Als het niet werkt

**"Het bestand is geen geldige JSON."** Er is niets aan de database veranderd.
Bijna altijd een komma te veel achter de laatste regel, een ontbrekend
aanhalingsteken, of een aanhalingsteken midden in een tekst die u hebt geplakt —
dat laatste moet worden voorafgegaan door een backslash. Het commando noemt de
positie.

**"Dit profiel verwacht 93 maatregelen, het bestand heeft 92."** Er is een regel
verdwenen, meestal bij het plakken. Vergelijk met uw kopie.

**"Veld 'omschrijving' ontbreekt of is leeg."** Een lege tekst is geen geldige
waarde. Wilt u bij een maatregel niets invullen, laat dan de meegeleverde
mededeling staan.

**Het bestand dat u wilt bewerken bestaat niet.** Dan kijkt u naar het bestand
van de andere norm. Kijk in de tabel hierboven welk bestand bij deze installatie
hoort.

**U hebt de `.env` aangepast en er verandert niets.** Op een uitgerolde
installatie wordt de configuratie één keer ingelezen en bewaard; een wijziging in
`.env` telt pas mee na:

```
php artisan config:cache
```

Een uitrol doet dit zelf, dus na een gewone uitrol hoeft u hier niets mee. Voor
het invoeren van normteksten maakt het niets uit: dit commando leest niets uit
`.env`.

## De vijfde attribuutdimensie

Los hiervan, en optioneel: de dimensie *capaciteiten* wordt evenmin meegeleverd,
omdat zowel de waardenlijst als de toewijzing per maatregel uit de norm komen.
Bezit u die, dan maakt u zelf `database/seeders/data/maatregel-capaciteiten.json`:

```json
{
  "vocabulaire": ["Governance", "Veilige configuratie", "…"],
  "regels": [ { "annex_a_referentie": "5.1", "capaciteiten": ["Governance"] } ]
}
```

En daarna:

```
php artisan isms:capaciteiten aan
```

Zie [Maatregelclassificatie](/kennisbank/maatregelclassificatie) voor wat die
dimensie is en waarom hij ontbreekt.
