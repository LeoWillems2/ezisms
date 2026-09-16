# De audit trail: wat er in staat, en wat niet

De audit trail is het logboek van wijzigingen aan het ISMS: **wie, wat, wanneer,
op welk blok**. De audit trail is append-only. Er is geen scherm en geen knop
waarmee een regel te wijzigen of te verwijderen is.

De audit trail staat onder **Bewijs & audit trail → Audit trail**. Volledige
inzage vereist `muteren`, `goedkeuren` of `exporteren` op dat blok. Een
Medewerker heeft op dat blok `uitvoeren` (eigen bewijs uploaden) en zou zonder
die beperking het handelen van iedereen kunnen doorlezen.

## Wat er in één regel staat

| Veld | Wat het is |
| --- | --- |
| Tijdstip | Het moment van de wijziging. |
| Gebruiker | De persoon die de handeling uitvoerde, als **naam én verwijzing**. Zie hieronder. |
| Blok | Het ISMS-blok waar de entiteit bij hoort. Een auditor filtert op dit veld. |
| Entiteit | Het type, het nummer en een leesbare omschrijving zoals die op dat moment was. |
| Actie | Aangemaakt, gewijzigd, status gewijzigd, verwijderd of geëxporteerd. |
| Wijziging | Per veld de oude en de nieuwe waarde. |

De naam van de gebruiker en de omschrijving van de entiteit staan in de regel als
**momentopname**, en niet alleen als verwijzing. Die dubbeling is bewust. Een
logregel die "Risico #14" toont waar op dat moment "Uitval fileserver" stond, is
als bewijs waardeloos. Als een account wordt verwijderd, blijft de naam in de
logregel staan. Anders verdwijnt juist het bewijs van wat die persoon deed.

Als de gebruiker **Systeem (geplande taak)** is, dan is de wijziging uitgevoerd
door een dagelijkse taak en niet door een mens. Voorbeelden zijn het vervallen van
accounts en het archiveren van bewijsstukken.

## De vijf acties

| Actie | Wanneer |
| --- | --- |
| `aangemaakt` | Er is een nieuw record. De beginwaarden staan erbij. |
| `gewijzigd` | Eén of meer velden zijn gewijzigd. De oude en de nieuwe waarde staan erbij. |
| `status_gewijzigd` | **Alleen** als de status het enige gewijzigde veld was. |
| `verwijderd` | Het record is verwijderd. De laatste waarden staan er nog. |
| `geexporteerd` | De inhoud van het ISMS is uitgeleverd. Deze actie raakt geen record; zie de verzamelingsregels hieronder. |

De derde actie vraagt de meeste aandacht. Een risico dat tegelijk werd
herbeoordeeld en van status veranderde, krijgt de actie `gewijzigd`. De
statusovergang staat dan in de kolom Wijziging en niet in de actie. **Een filter op
`status_gewijzigd` vindt daarom niet alle statusovergangen**; een deel ontbreekt
dan. De KPI's die statusovergangen tellen, gebruiken dit filter om die reden ook
niet.

## Alle entiteiten die een regel opleveren

Er zijn 46 entiteiten die naar de trail schrijven. Per blok:

| Blok | Entiteit | Waarover de regels gaan |
| --- | --- | --- |
| Identity, Access & Rollen | `gebruiker` | Account aangemaakt, uitgenodigd, gedeactiveerd, geblokkeerd, A.6-velden (NDA, screening, offboarding) |
| Identity, Access & Rollen | `rol_toewijzing` | Wie wanneer welke rol kreeg, en van wie |
| Identity, Access & Rollen | `externe_identiteit` | Aan welk account bij de identiteitsprovider een account gekoppeld is, en wanneer dat veranderde |
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
| Risicomanagement & SoA | `overheidsmaatregel_beoordeling` | Alleen in het BIO-profiel: per overheidsmaatregel de status, de onderbouwing en de verwijzing naar de risicoanalyse |
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

`bewijs_koppeling` is de enige entiteit zonder vast blok. Die regel komt op het
blok van het record waaraan het bewijs hangt. Bewijs dat aan een risico wordt
gekoppeld, staat dus onder Risicomanagement & SoA, waar de auditor het zoekt.

## Drie soorten regels die er anders uitzien

**Koppelingen.** Het wijzigen van een koppeling komt in de trail: welk beleid welke
maatregel dekt, wie in welke doelgroep zit en welke clausules binnen een
auditronde vallen. Het systeem schrijft één regel per handeling met de delta
erin, en niet één regel per gekoppelde rij. Anders levert het koppelen van de
normatieve scope van een auditronde honderdelf regels op in één klik. De waarde
leest als *"2 gekoppeld: A.8.2 …, A.8.3 …"*, met het aantal vooraan.

**Verzamelingsregels.** Een handeling die geen record raakt maar een verzameling,
levert één regel op zonder entiteitnummer, met "(verzameling)" in de kolom
Entiteit. Er zijn twee van zulke handelingen:

- het opruimen van raadplegingen na de bewaartermijn (`raadpleging`);
- een **export van het ISMS** (`isms_export`, blok Installatiebeheer). Deze regel
  legt vast dat de inhoud het systeem heeft verlaten, door wie en naar welke map.
  De regel legt niet vast wat erin staat, omdat dat het ISMS zelf is. Zonder deze
  regel is uitleveren een handeling zonder spoor, en juist daar kijkt een auditor
  naar.

**Verklaringen zonder wijziging.** Bij een handmatige KPI legt het systeem vast
dat de gebruiker heeft verklaard dat de meetmethode niet is veranderd. Dat is geen
wijziging, maar wel een uitspraak. Die uitspraak onderbouwt de vergelijkbaarheid
van de meetreeks.

## Wat er bewust niet in staat

Een auditor die vraagt wat de trail dekt, hoort ook te horen wat de trail niet
dekt.

- **Leesgedrag.** Het ophalen van een bewijsstuk komt in een aparte registratie
  (`raadplegingen`) en niet in de trail. Lezen gebeurt vaker dan muteren, heeft
  een eigen bewaartermijn en zou de mutaties overstemmen. Het doel van de
  registratie is beperkt en expliciet: onderbouwen of iemand die een
  leesbevestiging afgaf, het document ook daadwerkelijk had geopend.
- **Schermkopieën.** Wat als Word-document is meegegeven, staat in een eigen
  register (Bewijs & audit trail → Schermkopieën). Een kopie wijzigt niets, en
  twee soorten feiten in één tabel maken beide onleesbaar.
- **Wachtwoordhashes en tokens.** `wachtwoord` en `remember_token` op een account
  en het `token` van een toetsopdracht zijn uitgesloten. Dat is een
  beveiligingsmaatregel en geen opmaakkeuze. Zonder uitsluiting zou een
  wachtwoordhash leesbaar terechtkomen in een tabel die de Auditor mag inzien en
  exporteren.
- **Wijzigingen buiten de applicatie om.** Een wijziging die rechtstreeks in de
  database wordt geschreven, komt niet in de trail. Zie hieronder.

## Hoe hard is "append-only"?

In de applicatie is append-only hard afgedwongen, in de database niet vanzelf.

Het model weigert elke poging om een logregel te wijzigen of te verwijderen. Dat
is een vangnet tegen programmeerfouten en geen beveiligingsmaatregel. Iemand met
databasetoegang omzeilt het met één `UPDATE`. De werkelijke beheersmaatregel is
een grant op databaseniveau: het applicatieaccount krijgt `INSERT` en `SELECT` op
`audit_logregels`, en geen `UPDATE` of `DELETE`. Dat is een inrichtingsstap bij
het opzetten van de omgeving. Het is de moeite waard die stap aantoonbaar uit te
voeren, omdat een auditor precies op dit punt doorvraagt.

Voor ontwikkelaars geldt één valkuil. Een massa-update rechtstreeks op de database
(`Model::where(...)->update()`) vuurt geen model-events af en komt dus niet in de
trail. Daarvoor bestaan `updateGeaudit()` en `deleteGeaudit()`. De wijziging
gebeurt dan per record, en de trail registreert elke wijziging.

## De keten: wat er gebeurt als iemand er toch in schrijft

Elke logregel bevat de **hash van zijn voorganger**. Als er een regel wordt
verwijderd, gewijzigd of tussengevoegd, klopt de schakel niet meer. De controle
wijst dan de regel aan waar de keten breekt.

Die controle draait elke nacht om 01:45 (`isms:controleer-audittrail`). De uitslag
komt in een eigen tabel en staat bovenaan dit scherm: *keten intact t/m regel
zoveel, gecontroleerd op die datum*. Het bewaren van de uitslagen is het
wezenlijke punt. Een auditor vraagt niet of de keten vandaag klopt, maar of de
keten al twee jaar elke nacht is gecontroleerd.

**Wat een keten niet oplost.** Iemand die de database kan wijzigen, kan na een
wijziging alle volgende hashes opnieuw berekenen. Daarna klopt de keten weer.
Daartegen helpt maar één middel: een oudere **kophash** die buiten dit systeem
ligt. Daarom bevat de kopie voor de auditor van dit scherm die hash. Dat document
ligt na afloop buiten de invloed van de organisatie, en bij de volgende audit is
één vergelijking genoeg. De organisatie hoort het document daarom niet alleen zelf
te bewaren.

**Twee momenten waarop de keten opnieuw begint.** Bij het in gebruik nemen van
deze versie is de keten over de bestaande regels aangelegd. Dat legt de inhoud
vast zoals die op dat moment was, en zegt niets over wat er daarvóór is gebeurd.
Hetzelfde gebeurt na `isms:verwijder-auditdata --met-trail` en na een handmatige
ingreep in de database, bijvoorbeeld het verwijderen van een account. Zo'n
verzegeling wordt met datum en reden vastgelegd. Daardoor is zichtbaar waar de
bewijskracht van de trail begint.


## De trail als meetbron

De audit trail is niet alleen een naslagwerk. Drie KPI's meten rechtstreeks uit
de trail: het aantal **nieuw geïdentificeerde risico's** in een periode, het
aandeel **statusovergangen naar gemitigeerd**, en het aantal **scoredalingen
zonder bewijs** in dezelfde periode. Zie [KPI's en meetwaarden](kpis-en-meetwaarden).

De laatste KPI is het meest leerzame geval. Een dalende risicoscore is te sturen,
dus een scoredaling zonder bewijs in dezelfde periode is zelf een signaal. Dat is
alleen meetbaar omdat de trail vastlegt wanneer de score veranderde en wanneer het
bewijs eraan werd gekoppeld.

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
