Dit artikel beschrijft hoe een leverancier in het ISMS wordt opgevoerd, met een
**HR-SaaS** (een clouddienst voor personeelsadministratie) als voorbeeld. Een
HR-SaaS is een leerzaam voorbeeld, omdat de dienst **persoonsgegevens** verwerkt
en een **clouddienst** is. Die twee eigenschappen maken de eisen strenger.

## In het kort

Een leverancier doorloopt de statussen `kandidaat → actief → beëindigd`. De
leverancier gaat pas naar **actief** als de dienst, de classificatie, de
contractafspraken en een eerste beoordeling op orde zijn. De status **beëindigd**
is pas mogelijk als de teruggave van data is bevestigd. De onboarding bestaat dus
uit het invullen van dat dossier.

## Stap voor stap

**1. Registreer de leverancier (status `kandidaat`).**
In deze stap wordt alleen de naam vastgelegd. De status `kandidaat` betekent dat
de leverancier in beoordeling is en nog niet in gebruik.

**2. Leg de dienst vast en koppel het systeem.**
Onder de leverancier wordt een **dienst** toegevoegd ("personeelsadministratie").
Aan die dienst wordt het **systeem** gekoppeld: de HR-SaaS-applicatie uit het
asset- of systeemregister. Zo hangt de leverancier aan een concreet systeem in het
register. Die koppeling is nodig voor A.5.21 (ICT-toeleveringsketen) en voor de
impactanalyse.

**3. Classificeer de verwerkte gegevens.**
HR-data zijn **persoonsgegevens**, vaak van een bijzondere of gevoelige categorie
(BSN, salaris, verzuim). Die classificatie staat op het gekoppelde systeem of
asset en bepaalt de zwaarte van alle volgende eisen. Een hoge vertrouwelijkheid
leidt tot strengere contract- en auditeisen. Deze stap raakt A.5.34 (bescherming
van persoonsgegevens).

**4. Bepaal het risiconiveau en maak zo nodig een volwaardig risico aan.**
Het veld `risiconiveau` (`laag/midden/hoog`) is een **grof registerlabel** om op
te filteren en geen tweede risicomatrix. Bij een serieus risico wordt een
volwaardig **Risico** aangemaakt en aan de leverancier gekoppeld. Bij een HR-SaaS
met persoonsgegevens in de cloud is het niveau al snel `hoog`. De eigenlijke
beoordeling en behandeling vinden plaats in dat risico.

**5. Leg de contractafspraken vast.**
Per leverancier wordt geregistreerd of de volgende clausules **aanwezig** zijn:

- **`vertrouwelijkheid`**: geheimhouding. Bij HR-data is deze clausule onmisbaar.
- **`recht_op_audit`**: het recht om de leverancier te toetsen of te laten toetsen.
  Als de leverancier een geldig eigen ISO 27001-certificaat heeft, wordt
  `eigen_certificering_geldig_tot` ingevuld. Dat certificaat telt als tweede
  manier om aan deze eis te voldoen.
- **`sla`**: afspraken over beschikbaarheid en continuïteit.
- **`incidentmeldplicht`**: de leverancier moet beveiligingsincidenten, waaronder
  datalekken, melden. Deze clausule sluit aan op incidentbeheer.

> **Signaal voor een gat.** Het systeem markeert een leverancier op risiconiveau
> `hoog` die **geen** recht op audit en ook geen geldig eigen certificaat heeft
> (`isHoogRisicoZonderAuditrecht`). Bij een HR-SaaS hoort dit signaal op groen te
> staan.

Voor HR-data hoort hier ook een **verwerkersovereenkomst (AVG/GDPR-DPA)** bij. Die
overeenkomst wordt als bewijsstuk vastgelegd en aan de leverancier gekoppeld.
ISO 27001 dekt dit via A.5.20 (afspraken) en A.5.34 (persoonsgegevens). De AVG is
de aanvullende juridische laag.

**6. Voer de eerste leveranciersbeoordeling uit.**
Er wordt een **beoordeling** vastgelegd (datum, bevindingen) en de **volgende
beoordeling wordt gepland** (`volgende_beoordeling_gepland`). Het systeem
signaleert wanneer de datum van een herbeoordeling is verstreken. Dat dekt A.5.22
(monitoren en herzien).

**7. Zet de status op `actief`.**
Deze stap volgt pas als stap 2 tot en met 6 op orde zijn. Daarna is de leverancier
"in gebruik" en telt de leverancier mee in de rapportages.

## Tijdens de looptijd

- **Periodieke herbeoordeling** gebeurt volgens de geplande datum (A.5.22).
- **Incidenten** die de leverancier meldt, of die bij de leverancier spelen, lopen
  via incidentbeheer. De clausule `incidentmeldplicht` borgt dat de organisatie
  die meldingen ontvangt.
- **Wijzigingen** in de dienst of het risicoprofiel worden bijgewerkt en opnieuw
  beoordeeld.

## De andere kant: welke assets zijn de reden?

De verwerkersovereenkomst legt vast wat de leverancier met persoonsgegevens mag
doen. Aan de assetkant wordt vastgelegd **welke gegevens dat zijn**. Elk asset
heeft bij de classificatie een veld **Persoonsgegevens**, met vier waarden uit de
AVG: geen, gewone (art. 4), bijzondere (art. 9, waaronder gezondheid) en
strafrechtelijke (art. 10).

Twee punten zijn daarbij van belang:

- **Leeg is niet hetzelfde als "geen".** Een leeg veld betekent dat niemand de
  vraag heeft gesteld. De waarde `geen` betekent dat iemand de vraag heeft gesteld
  en met nee heeft beantwoord. Het filter op het assetoverzicht heeft daarom een
  aparte stand "Nog niet beoordeeld". Die lijst toont de assets die nog aandacht
  nodig hebben.
- **Bijzondere en strafrechtelijke gegevens horen minstens op `vertrouwelijk`.**
  Bij een lagere classificatie verschijnt een waarschuwing. Die waarschuwing
  blokkeert niets. Het ISMS moet de werkelijke situatie kunnen vastleggen, en juist
  het verschil met de gewenste situatie hoort zichtbaar te zijn.

Bij een HR-SaaS is dit het asset dat de personeelsadministratie bevat. Als dat
asset op `gewoon` of hoger staat, is de verwerkersovereenkomst geen formaliteit
maar een eis. Bij een incident op dat asset is de vraag naar de externe meldplicht
dan ook niet vrijblijvend.

## Beëindiging: de teruggave van data

De status kan **niet** naar `beëindigd` zolang de **teruggave of verwijdering van
data niet is bevestigd** (`belemmeringVoorBeeindigen`). Bij een HR-SaaS is dit het
zwaarst wegende punt. De organisatie bevestigt en legt vast dat de
persoonsgegevens zijn teruggegeven of vernietigd, wie dat heeft bevestigd en
wanneer. Dit borgt A.8.10 (verwijderen van informatie) en het exit-deel van
A.5.20.

## Aansluiting op ISO/IEC 27001

| Eis | Plaats in de opzet |
|---|---|
| **A.5.19** Beveiliging in leveranciersrelaties | Het leveranciersdossier als geheel |
| **A.5.20** Afspraken in leveranciersovereenkomsten | Contractclausules + verwerkersovereenkomst als bewijs |
| **A.5.21** Beveiliging in de ICT-toeleveringsketen | Dienst gekoppeld aan systeem/asset |
| **A.5.22** Monitoren en herzien van leveranciersdiensten | Leveranciersbeoordeling + geplande herbeoordeling |
| **A.5.23** Beveiliging bij gebruik van clouddiensten | De SaaS als gekoppeld systeem, met classificatie en clausules |
| **A.5.34** Bescherming van persoonsgegevens (PII) | Classificatie HR-data + verwerkersovereenkomst |
| **A.8.10** Verwijderen van informatie | Bevestigde data-teruggave bij beëindiging |

**Samengevat:** een HR-SaaS wordt opgevoerd door de dienst aan een systeem te
koppelen, de persoonsgegevens te classificeren, het risico volwaardig te
beoordelen, de vier contractclausules en de verwerkersovereenkomst vast te leggen
en de leverancier periodiek te herbeoordelen. De relatie wordt pas afgesloten als
de teruggave van data aantoonbaar is bevestigd.
