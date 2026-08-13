# De audit trail: wat er in staat, en wat niet

De audit trail is het logboek van wijzigingen aan het ISMS: **wie, wat, wanneer,
op welk blok**. Hij is append-only — er is geen scherm en geen knop waarmee een
regel te wijzigen of te verwijderen valt.

U vindt hem onder **Bewijs & audit trail → Audit trail**. Volledige inzage
vereist `muteren`, `goedkeuren` of `exporteren` op dat blok: een Medewerker heeft
er `uitvoeren` (eigen bewijs uploaden) en zou anders ieders handelen kunnen
doorlezen.

## Wat er in één regel staat

| Veld | Wat het is |
| --- | --- |
| Tijdstip | Het moment van de wijziging. |
| Gebruiker | Wie de handeling deed — als **naam én verwijzing**. Zie hieronder. |
| Blok | Het ISMS-blok waar de entiteit bij hoort; hier filtert een auditor op. |
| Entiteit | Type, nummer, en een leesbare omschrijving zoals die op dat moment was. |
| Actie | Aangemaakt, gewijzigd, status gewijzigd, verwijderd of geëxporteerd. |
| Wijziging | Per veld de oude en de nieuwe waarde. |

De naam van de gebruiker en de omschrijving van de entiteit staan er als
**momentopname** bij, niet alleen als verwijzing. Dat is bewuste dubbeling: een
logregel die "Risico #14" toont waar toen "Uitval fileserver" stond, is als
bewijs waardeloos. Wordt een account verwijderd, dan blijft de naam in de
logregel staan — anders verdwijnt juist het bewijs van wat die persoon deed.

Staat er **Systeem (geplande taak)** als gebruiker, dan is de wijziging door een
dagelijkse taak gedaan en niet door een mens. Bijvoorbeeld het vervallen van
accounts of het archiveren van bewijsstukken.

## De vijf acties

| Actie | Wanneer |
| --- | --- |
| `aangemaakt` | Een nieuw record. De beginwaarden staan erbij. |
| `gewijzigd` | Eén of meer velden gewijzigd, met oud en nieuw. |
| `status_gewijzigd` | **Alleen** als de status het enige gewijzigde veld was. |
| `verwijderd` | Het record is weg; de laatste waarden staan er nog. |
| `geexporteerd` | De inhoud van het ISMS is uitgeleverd. Raakt geen record; zie de verzamelingsregels hieronder. |

Die derde is de subtiele. Een risico dat tegelijk werd herbeoordeeld *en* van
status veranderde, draagt actie `gewijzigd` — de statusovergang zit dan in de
kolom Wijziging, niet in de actie. **Filter dus niet op `status_gewijzigd` als u
alle statusovergangen zoekt**; u mist er dan een deel van. De KPI's die
statusovergangen tellen, doen dat om die reden ook niet.

## Alle entiteiten die een regel opleveren

Vierenveertig entiteiten schrijven naar de trail. Per blok:

| Blok | Entiteit | Waarover de regels gaan |
| --- | --- | --- |
| Identity, Access & Rollen | `gebruiker` | Account aangemaakt, uitgenodigd, gedeactiveerd, geblokkeerd, A.6-velden (NDA, screening, offboarding) |
| Identity, Access & Rollen | `rol_toewijzing` | Wie wanneer welke rol kreeg, en van wie |
| Context & Scope | `scope_verklaring` | Scopetekst, versie, indienen en activeren |
| Context & Scope | `uitsluiting` | Uitgesloten maatregelen met motivatie |
| Asset & Informatie-classificatie | `asset` | Registratie, classificatie (BIV), eigenaarschap, afstoten |
| Asset & Informatie-classificatie | `asset_toewijzing` | Uitgifte en teruggave per persoon |
| Asset & Informatie-classificatie | `systeem` | Systemen, beschikbaarheidseis, redundantie, afvoeren |
| Risicomanagement & SoA | `risico` | Identificatie, beoordeling (kans/impact), eigenaar, status |
| Risicomanagement & SoA | `risicobehandeling` | Behandeloptie, restrisico, acceptatie door de directie |
| Risicomanagement & SoA | `soa_regel` | Van toepassing ja/nee met motivatie, implementatiestatus, eigen classificatie |
| Risicomanagement & SoA | `risicocriteria_versie` | Het vastgestelde kader: de drempelwaarden, de risk appetite, indienen en activeren |
| Risicomanagement & SoA | `beoordelingsniveau` | Wat een niveau van kans of impact betekent, per criteriaversie |
| Risicomanagement & SoA | `restrisico_snapshot` | De jaarlijkse vastlegging per control en de toelichting erop |
| Bewijsrepository & Audit Trail | `bewijsstuk` | Upload, metadata, archiveren, bewaartermijn |
| Bewijsrepository & Audit Trail | `bewijs_koppeling` | Bewijs aan een record hangen of loskoppelen |
| Taken & Workflow | `taak` | Aanmaken, toewijzen, afronden, verlopen |
| Taken & Workflow | `taaksjabloon` | De terugkerende taken en hun ritme |
| Beleid & Maatregelbeheer | `beleidsdocument` | Titel, eigenaar, bevestigingsplicht, doelgroep |
| Beleid & Maatregelbeheer | `beleidsversie` | Versies, vaststellen, publiceren, intrekken |
| Beleid & Maatregelbeheer | `leesbevestiging` | Wie welk beleid heeft bevestigd |
| Incident- & Afwijkingenbeheer | `incident` | Melding, categorie, statusverloop, sluiten |
| Incident- & Afwijkingenbeheer | `incident_melding` | Externe meldplicht: welke verplichting, welke termijn, wanneer afgevinkt |
| Incident- & Afwijkingenbeheer | `afwijking` | Registratie, herkomst, sluiten |
| Incident- & Afwijkingenbeheer | `grondoorzaak` | De analyse onder een afwijking |
| Incident- & Afwijkingenbeheer | `corrigerende_maatregel` | Wat er is afgesproken en door wie |
| Incident- & Afwijkingenbeheer | `effectiviteitstoets` | Of de maatregel heeft gewerkt |
| Leveranciers & Derdenrisico | `leverancier` | Registratie, status, beëindiging en teruggave |
| Leveranciers & Derdenrisico | `leveranciersbeoordeling` | Periodieke beoordelingen en hun uitkomst |
| Bewustzijn, Training & Toetsen | `trainingsmodule` | Modules, geldigheidsduur, doelgroepen |
| Bewustzijn, Training & Toetsen | `doelgroep` | Doelgroepen en hun leden |
| Bewustzijn, Training & Toetsen | `trainingsvoltooiing` | Wie wanneer welke training afrondde |
| Bewustzijn, Training & Toetsen | `toetsopdracht` | Uitgezette toetsen en hun uitslag |
| Auditmanagement | `auditprogramma` | Het meerjarenprogramma |
| Auditmanagement | `auditplan` | Het jaarplan |
| Auditmanagement | `auditronde` | Planning, scope, auditor, afronden |
| Auditmanagement | `bevinding` | Non-conformiteiten en observaties |
| Management Review & Verbetercyclus | `reviewsessie` | Sessies, agenda, besluiten |
| Management Review & Verbetercyclus | `verbeteractie` | Acties uit de review en hun voortgang |
| Management Review & Verbetercyclus | `kpi_definitie` | Meetaanpak, richting, streefwaarde vaststellen |
| Notificatie & Integratie | `notificatieregel` | Welke gebeurtenis naar wie gaat |
| Notificatie & Integratie | `integratie_adapter` | Koppelingen met externe systemen |
| Wijzigingsbeheer | `wijziging` | Het dossier: planning, terugvalplan, uitvoering, evaluatie |
| Wijzigingsbeheer | `wijzigingssjabloon` | Welke route een soort wijziging volgt |
| Wijzigingsbeheer | `sjabloonstap` | De stappen in die route, inclusief goedkeuringspunten |

`bewijs_koppeling` is de enige die geen vast blok heeft: die regel landt op het
blok van het record waaraan het bewijs hangt. Bewijs aan een risico koppelen komt
dus onder Risicomanagement & SoA te staan, waar de auditor het zoekt.

## Drie soorten regels die er anders uitzien

**Koppelingen.** Sinds 3 augustus 2026 komt ook het wijzigen van een koppeling in
de trail: welk beleid welke maatregel dekt, wie in welke doelgroep zit, welke
clausules binnen een auditronde vallen. Eén regel per handeling met de delta
erin, niet één per gekoppelde rij — de normatieve scope van een auditronde
koppelt anders honderdelf regels in één klik. De waarde leest als
*"2 gekoppeld: A.8.2 …, A.8.3 …"*, met het aantal vooraan.

**Verzamelingsregels.** Een handeling die geen record raakt maar een verzameling
krijgt één regel zonder entiteitnummer, met "(verzameling)" in de kolom Entiteit.
Er zijn er twee:

- het opruimen van raadplegingen na de bewaartermijn (`raadpleging`);
- een **export van het ISMS** (`isms_export`, blok Installatiebeheer). Die legt
  vast dát de inhoud het systeem heeft verlaten, door wie en naar welke map —
  niet wát erin staat, want dat is het ISMS zelf. Zonder die regel is uitleveren
  een handeling zonder spoor, en juist daar kijkt een auditor naar.

**Verklaringen zonder wijziging.** Bij een handmatige KPI legt het systeem vast
dát u hebt verklaard dat de meetmethode níét is veranderd. Dat is geen wijziging
maar wel een uitspraak, en juist die uitspraak draagt de vergelijkbaarheid van de
reeks.

## Wat er bewust níét in staat

Een auditor die vraagt wat de trail dekt, verdient ook het antwoord op wat hij
niet dekt.

- **Leesgedrag.** Wie een bewijsstuk ophaalt komt in een aparte registratie
  (`raadplegingen`), niet in de trail. Lezen gebeurt vaker dan muteren, heeft een
  eigen bewaartermijn, en zou de mutaties verdrinken. Het doel is beperkt en
  expliciet: onderbouwen of iemand die een leesbevestiging afgaf het document ook
  daadwerkelijk had.
- **Schermkopieën.** Wat er als Word-document is meegegeven staat in een eigen
  register (Bewijs & audit trail → Schermkopieën). Een kopie wijzigt niets, en
  twee soorten feiten in één tabel maakt beide onleesbaar.
- **Wachtwoordhashes en tokens.** `wachtwoord` en `remember_token` op een account
  en het `token` van een toetsopdracht zijn uitgesloten. Dat is een
  beveiligingscontrole en geen opmaakkeuze: zonder uitsluiting zou een
  wachtwoordhash leesbaar belanden in een tabel die de Auditor mag inzien én
  exporteren.
- **Koppelingen van vóór 3 augustus 2026.** Die dragen geen datum en geen naam,
  en van koppelingen die daarvóór zijn weggehaald bestaat geen enkel spoor. Het
  is niet met terugwerkende kracht te maken; doen alsof van wel is erger dan de
  beperking noemen.
- **Wijzigingen buiten de applicatie om.** Wie rechtstreeks in de database
  schrijft, komt niet in de trail. Zie hieronder.

## Hoe hard is "append-only"?

Eerlijk antwoord: **in de applicatie hard, in de database niet vanzelf.**

Het model weigert elke poging tot wijzigen of verwijderen van een logregel. Dat
is een vangnet tegen programmeerfouten, geen beveiligingscontrole — wie
databasetoegang heeft, omzeilt het met één `UPDATE`. De echte controle is een
grant op databaseniveau: het applicatieaccount krijgt `INSERT` en `SELECT` op
`audit_logregels`, geen `UPDATE` of `DELETE`. Dat is een inrichtingsstap bij het
opzetten van de omgeving, en het is de moeite waard hem aantoonbaar te doen: dit
is precies het punt waarop een auditor doorvraagt.

Eén valkuil voor wie meebouwt: een massa-update rechtstreeks op de database
(`Model::where(...)->update()`) vuurt geen model-events en komt dus niet in de
trail. Daar zijn `updateGeaudit()` en `deleteGeaudit()` voor; de wijziging
gebeurt dan per record en de trail loopt gewoon mee.

## De keten: wat er gebeurt als iemand er tóch in schrijft

Elke logregel draagt de **hash van zijn voorganger**. Wordt er een regel
verwijderd, gewijzigd of tussengevoegd, dan klopt de schakel niet meer en wijst
de controle de regel aan waar het misgaat.

Die controle draait elke nacht om 01:45 (`isms:controleer-audittrail`) en de
uitslag komt in een eigen tabel — u ziet hem bovenaan dit scherm staan: *keten
intact t/m regel zoveel, gecontroleerd op die datum*. Dat de uitslagen bewaard
blijven is het punt: een auditor vraagt niet of de keten vandaag klopt, maar of
hij al twee jaar elke nacht is gecontroleerd.

**Wat een keten niet oplost.** Wie de database kan wijzigen, kan na een wijziging
alle volgende hashes opnieuw uitrekenen; dan klopt de keten weer. Daar helpt maar
één ding tegen: een oudere **kophash** die búiten dit systeem ligt. Vandaar dat
de kopie voor de auditor van dit scherm die hash draagt — dat document ligt na
afloop buiten uw invloed, en bij de volgende audit is één vergelijking genoeg.
Bewaar het dus niet alleen zelf.

**Twee momenten waarop de keten opnieuw begint.** Bij het in gebruik nemen van
deze versie is de keten over de bestaande regels aangelegd; dat legt de inhoud
vast zoals die op dat moment was en zegt niets over wat er daarvóór is gebeurd.
Hetzelfde gebeurt na `isms:verwijder-auditdata --met-trail` en na een handmatige
ingreep in de database, bijvoorbeeld het verwijderen van een account. Zo'n
verzegeling wordt met datum en reden vastgelegd — het is zichtbaar waar de
bewijskracht van de trail begint.


## De trail als meetbron

De audit trail is niet alleen een naslagwerk. Drie KPI's meten er rechtstreeks
uit: het aantal **nieuw geïdentificeerde risico's** in een periode, het aandeel
**statusovergangen naar gemitigeerd**, en **scoredalingen zonder bewijs** in
dezelfde periode. Zie [KPI's en meetwaarden](kpis-en-meetwaarden).

Dat laatste is het aardigste geval: een dalende risicoscore is te sturen, dus een
scoredaling zónder bewijs in dezelfde periode is zelf een signaal. Dat is alleen
meetbaar omdat de trail vastlegt wanneer die score veranderde en wanneer het
bewijs eraan werd gehangen.

## Normkoppeling

| Onderdeel | Annex A / hoofdstuk |
| --- | --- |
| Logging van gebeurtenissen, en het beschermen van die logs | A.8.15 |
| Beschermen van registraties | A.5.33 |
| Verzamelen van bewijsmateriaal | A.5.28 |
| Toekennen en intrekken van toegang, gelogd | A.5.18 |
| Beheersen van gedocumenteerde informatie | 7.5.3 |
| Bewijs voor de interne audit en de directiebeoordeling | 9.2, 9.3 |
| Bewijs bij afwijkingen en corrigerende maatregelen | 10.2 |
