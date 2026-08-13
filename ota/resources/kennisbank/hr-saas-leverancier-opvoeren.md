Zo voer je een leverancier op in het ISMS, met een **HR-SaaS** (een clouddienst
voor personeelsadministratie) als voorbeeld. Een HR-SaaS is leerzaam omdat er
**persoonsgegevens** langs gaan én het een **clouddienst** is — de twee dingen
die de eisen aanscherpen. De stappen volgen de leveranciersmodule (blok 9,
`/leveranciers`).

## In het kort

Een leverancier doorloopt `kandidaat → actief → beëindigd`. Je zet hem pas op
**actief** als de dienst, de classificatie, de contractafspraken en een eerste
beoordeling op orde zijn — en op **beëindigd** kan pas als de teruggave van data
is bevestigd. De onboarding is dus het invullen van dat dossier.

## Stap voor stap

**1. Registreer de leverancier (status `kandidaat`).**
Naam, en verder nog niets vastgelegd. `kandidaat` betekent: in beoordeling, nog
niet in gebruik.

**2. Leg de dienst vast en koppel het systeem.**
Voeg onder de leverancier een **dienst** toe ("personeelsadministratie") en
koppel het **systeem** (de HR-SaaS-applicatie uit het asset-/systeemregister,
blok 3). Zo hangt de leverancier aan een concreet systeem in het register —
nodig voor A.5.21 (ICT-toeleveringsketen) en voor de impactanalyse.

**3. Classificeer wat er langs gaat.**
HR-data is **persoonsgegevens** (vaak bijzondere/gevoelige categorie: BSN,
salaris, verzuim). Die classificatie staat op het gekoppelde systeem/asset (blok
3) en bepaalt de zwaarte van alle eisen hierna. Hoge vertrouwelijkheid → strengere
contract- en audit-eisen. Dit raakt A.5.34 (bescherming van persoonsgegevens).

**4. Bepaal het risiconiveau — en maak zo nodig een écht risico.**
Het veld `risiconiveau` (`laag/midden/hoog`) is een **grof registerlabel** om op
te filteren, géén tweede risicomatrix. Is het serieus — en bij een HR-SaaS met
persoonsgegevens in de cloud is dat al snel `hoog` — leg dan een echt **Risico**
aan (blok 4) en koppel dat aan de leverancier. Daar gebeurt de eigenlijke
beoordeling en behandeling.

**5. Leg de contractafspraken vast.**
Per leverancier registreer je of deze clausules **aanwezig** zijn:

- **`vertrouwelijkheid`** — geheimhouding; bij HR-data onmisbaar.
- **`recht_op_audit`** — recht om te (laten) toetsen. Als de leverancier een
  geldig eigen ISO 27001-certificaat heeft, vul dan
  `eigen_certificering_geldig_tot` in: dat telt als tweede manier om dit aan te
  tonen.
- **`sla`** — afspraken over beschikbaarheid/continuïteit.
- **`incidentmeldplicht`** — de leverancier moet beveiligingsincidenten (waaronder
  datalekken) melden; dit haakt aan incidentbeheer (blok 8).

> **Gap-signaal.** Een leverancier op risiconiveau `hoog` **zonder** recht op
> audit én zonder geldig eigen certificaat wordt door het systeem gemarkeerd
> (`isHoogRisicoZonderAuditrecht`). Bij een HR-SaaS wil je dit signaal op groen.

Voor HR-data hoort hier ook een **verwerkersovereenkomst (AVG/GDPR-DPA)** bij; leg
die als bewijsstuk vast en koppel hem aan de leverancier. ISO 27001 dekt dit via
A.5.20 (afspraken) en A.5.34 (persoonsgegevens); de AVG is de aanvullende
juridische laag.

**6. Voer de eerste leveranciersbeoordeling uit.**
Leg een **beoordeling** vast (datum, bevindingen) en **plan de volgende**
(`volgende_beoordeling_gepland`). Het systeem signaleert wanneer een
herbeoordeling verstreken is — dat is A.5.22 (monitoren en herzien).

**7. Zet de status op `actief`.**
Pas als stap 2–6 op orde zijn. Nu is de leverancier "in gebruik" en telt hij mee
in de rapportages.

## Tijdens de looptijd

- **Periodiek herbeoordelen** volgens de geplande datum (A.5.22).
- **Incidenten** die de leverancier meldt (of die bij hem spelen) lopen via
  incidentbeheer (blok 8); de `incidentmeldplicht`-clausule borgt dat je ze krijgt.
- **Wijzigingen** in de dienst of het risicoprofiel: bijwerken en opnieuw
  beoordelen.

## De andere kant: welke assets zijn de reden?

De verwerkersovereenkomst legt vast wat de leverancier met persoonsgegevens mag.
Aan de assetkant leg je vast **wélke gegevens dat zijn**: op elk asset staat bij
de classificatie een veld **Persoonsgegevens**, met vier waarden die uit de AVG
komen — geen, gewone (art. 4), bijzondere (art. 9, waaronder gezondheid) en
strafrechtelijke (art. 10).

Twee dingen om te weten:

- **Leeg is niet hetzelfde als "geen".** Een leeg veld betekent dat niemand de
  vraag heeft gesteld; `geen` betekent dat iemand hem heeft gesteld en met nee
  heeft beantwoord. Het filter op het assetoverzicht heeft daarom een aparte
  stand "Nog niet beoordeeld" — dat is de lijst die je wilt zien.
- **Bijzondere en strafrechtelijke gegevens horen minstens op `vertrouwelijk`.**
  Staat er iets lagers, dan verschijnt daar een waarschuwing. Die blokkeert niets:
  het ISMS moet kunnen vastleggen hoe het ís, en dat verschil is nu juist wat je
  wilt kunnen zien.

Bij een HR-SaaS is dit het asset waar de personeelsadministratie in zit. Staat
dat op `gewoon` of hoger, dan is de verwerkersovereenkomst geen formaliteit maar
een eis — en bij een incident op dat asset is de vraag naar de externe meldplicht
niet vrijblijvend.

## Beëindiging — de teruggave van data

De status kan **niet** naar `beëindigd` zonder dat de **teruggave/verwijdering
van data is bevestigd** (`belemmeringVoorBeeindigen`). Bij een HR-SaaS is dit het
zwaarst wegende punt: bevestig en leg vast dat de persoonsgegevens zijn
teruggegeven of vernietigd, wie dat bevestigde en wanneer. Dit borgt A.8.10
(verwijderen van informatie) en het exit-deel van A.5.20.

## Aansluiting op ISO/IEC 27001

| Eis | Waar in de opzet |
|---|---|
| **A.5.19** Beveiliging in leveranciersrelaties | Het leveranciersdossier als geheel |
| **A.5.20** Afspraken in leveranciersovereenkomsten | Contractclausules + verwerkersovereenkomst als bewijs |
| **A.5.21** Beveiliging in de ICT-toeleveringsketen | Dienst gekoppeld aan systeem/asset |
| **A.5.22** Monitoren en herzien van leveranciersdiensten | Leveranciersbeoordeling + geplande herbeoordeling |
| **A.5.23** Beveiliging bij gebruik van clouddiensten | De SaaS als gekoppeld systeem, met classificatie en clausules |
| **A.5.34** Bescherming van persoonsgegevens (PII) | Classificatie HR-data + verwerkersovereenkomst |
| **A.8.10** Verwijderen van informatie | Bevestigde data-teruggave bij beëindiging |

**Samengevat:** een HR-SaaS voer je op door hem aan een systeem te koppelen, de
persoonsgegevens te classificeren, het risico echt te beoordelen, de vier
contractclausules (plus verwerkersovereenkomst) vast te leggen en periodiek te
herbeoordelen — en je sluit de relatie pas af als de teruggave van data
aantoonbaar is bevestigd.
