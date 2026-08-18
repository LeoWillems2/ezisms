<?php

namespace App\Livewire;

use App\Livewire\Concerns\LevertSchermkopie;
use App\Models\Maatregel;
use App\Models\OverheidsmaatregelBeoordeling;
use App\Models\SoaRegel;
use App\Support\Maatregelbalans;
use App\Support\Maatregelkenmerken;
use App\Support\Normprofiel;
use App\Support\Overheidsmaatregeldekking;
use App\Support\Schermkopie;
use App\Support\Schermkopiebijlage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SoaOverzicht extends Component
{
    use LevertSchermkopie;

    private const THEMAS = ['organisatorisch', 'mensgericht', 'fysiek', 'technologisch'];

    private const STATUSSEN = ['nvt', 'niet_gestart', 'in_uitvoering', 'geimplementeerd'];

    /** Eén plek voor het label, zodat scherm en schermkopie niet uiteenlopen. */
    private const STATUS_LABELS = [
        'nvt' => 'Niet van toepassing',
        'niet_gestart' => 'Niet gestart',
        'in_uitvoering' => 'In uitvoering',
        'geimplementeerd' => 'Geïmplementeerd',
    ];

    #[Url]
    public string $filterThema = '';

    #[Url]
    public string $filterStatus = '';

    /** Alleen de nog onbeoordeelde regels (van_toepassing is null) tonen. */
    #[Url]
    public bool $alleenOnbeslist = false;

    // Bewerkformulier.
    public ?int $bewerkteRegelId = null;

    public bool $toontFormulier = false;

    /** Radiogroep met drie standen; '' = onbeslist, '1' = ja, '0' = nee. */
    public string $vanToepassing = '';

    public string $motivatie = '';

    /** Korte eigen verwijzingen: bijv. een hoofdstuknummer in beleid/proces. */
    public string $beleidreferentie = '';

    public string $procesreferentie = '';

    public string $implementatiestatus = 'niet_gestart';

    /**
     * De eigen maatregelclassificatie (plan 04d fase 3): dimensiesleutel =>
     * aangevinkte waarden. Bij het openen voorgevuld met `kenmerken()`, dus bij
     * een eerste bewerking met het meegeleverde uitgangspunt.
     *
     * Voorvullen is nadrukkelijk niet hetzelfde als vaststellen: pas na opslaan
     * staat er iets van de organisatie zelf.
     *
     * @var array<string, list<string>>
     */
    public array $kenmerken = [];

    /**
     * De SoA-regels waarvan de BIO-verplichtingen in de tabel openstaan
     * (deelproducten/04c §3.1).
     *
     * Bewust géén `#[Url]`: de uitklapstand is vluchtig en hoort niet in een
     * deelbare link. Hij weegt ook niet mee in de schermkopie — een auditdocument
     * dat afhangt van welke rijen iemand toevallig had opengeklikt, is niet
     * reproduceerbaar (04c §7).
     *
     * @var list<int>
     */
    public array $uitgeklapt = [];

    /**
     * De beoordeling waarvan het bewijspaneel openstaat, of `null`.
     *
     * Eén tegelijk. Onder één beheersmaatregel hangen tot zeven verplichtingen en
     * elk paneel is een eigen Livewire-component met eigen state en eigen
     * verzoeken; zeven daarvan in één modal is niet wat de gebruiker vraagt en wel
     * wat de browser merkt.
     */
    public ?int $bewijsVoor = null;

    /**
     * De beoordeling per BIO-overheidsmaatregel (deelproducten/04b §3.1):
     * beoordeling-id => `['status' => …, 'motivatie' => …, 'risicobehandeling_id' => …]`.
     *
     * Alleen gevuld in een installatie met de capaciteit `overheidsmaatregelen`.
     * De sleutel is het id van de beoordeling en niet van de overheidsmaatregel:
     * de rij bestaat al (de seeder maakt hem aan), dus er valt niets aan te maken
     * en alleen bij te werken.
     *
     * @var array<int, array<string, string>>
     */
    public array $beoordelingen = [];

    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['risico-soa', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['risico-soa', 'muteren']);
    }

    /**
     * Klapt de BIO-verplichtingen onder één beheersmaatregel open of dicht.
     *
     * Geen autorisatiecheck: er wordt niets gemuteerd en de rijen staan al op het
     * scherm. Wel de controle dát de regel bij de getoonde verzameling hoort — een
     * id uit een aangepast verzoek hoort geen rij te openen die het filter net
     * heeft weggelaten.
     */
    public function klapUit(int $regelId): void
    {
        if (in_array($regelId, $this->uitgeklapt, true)) {
            $this->uitgeklapt = array_values(array_diff($this->uitgeklapt, [$regelId]));

            return;
        }

        $zichtbaar = $this->gefilterdeMaatregelen()
            ->pluck('soaRegel.id')
            ->filter()
            ->all();

        if (in_array($regelId, $zichtbaar, true)) {
            $this->uitgeklapt[] = $regelId;
        }
    }

    public function isUitgeklapt(int $regelId): bool
    {
        return in_array($regelId, $this->uitgeklapt, true);
    }

    /** Eén bewijspaneel tegelijk; nogmaals klikken sluit het. */
    public function toonBewijs(int $beoordelingId): void
    {
        $this->bewijsVoor = $this->bewijsVoor === $beoordelingId ? null : $beoordelingId;
    }

    public function getBewerkteRegelProperty(): ?SoaRegel
    {
        if (! $this->bewerkteRegelId) {
            return null;
        }

        return SoaRegel::with([
            'maatregel',
            // Alleen in een BIO-installatie: elders is de relatie leeg en zou het
            // een query zijn die nooit iets oplevert.
            ...(Normprofiel::heeft('overheidsmaatregelen')
                ? [
                    'overheidsmaatregelBeoordelingen.overheidsmaatregel',
                    'overheidsmaatregelBeoordelingen.bewijsKoppelingen',
                    'risicobehandelingen.risico',
                ]
                : []),
        ])->find($this->bewerkteRegelId);
    }

    public function bewerk(int $regelId): void
    {
        $this->vereisMuteren();

        $regel = SoaRegel::findOrFail($regelId);

        $this->bewerkteRegelId = $regel->id;
        $this->bewijsVoor = null;
        $this->vanToepassing = $regel->van_toepassing === null ? '' : ($regel->van_toepassing ? '1' : '0');
        $this->motivatie = $regel->motivatie ?? '';
        $this->beleidreferentie = $regel->beleidreferentie ?? '';
        $this->procesreferentie = $regel->procesreferentie ?? '';
        $this->implementatiestatus = $regel->implementatiestatus;
        $this->laadKenmerken($regel);
        $this->laadBeoordelingen($regel);

        $this->resetValidation();
        $this->toontFormulier = true;
    }

    /**
     * Vult het BIO-formulier met de stand zoals die is opgeslagen.
     *
     * Buiten een BIO-installatie blijft de array leeg, en daarmee doet de rest van
     * de component er niets. Geen `if` op het profiel dus, maar op de vraag of er
     * iets te beoordelen ís.
     */
    private function laadBeoordelingen(SoaRegel $regel): void
    {
        $this->beoordelingen = [];

        if (! Normprofiel::heeft('overheidsmaatregelen')) {
            return;
        }

        foreach ($regel->overheidsmaatregelBeoordelingen as $beoordeling) {
            $this->beoordelingen[$beoordeling->id] = [
                'status' => $beoordeling->status,
                'motivatie' => $beoordeling->motivatie ?? '',
                'beleidreferentie' => $beoordeling->beleidreferentie ?? '',
                'procesreferentie' => $beoordeling->procesreferentie ?? '',
                'risicobehandeling_id' => (string) ($beoordeling->risicobehandeling_id ?? ''),
            ];
        }
    }

    /**
     * Vult het classificatieformulier met de effectieve classificatie, beperkt
     * tot de actieve dimensies. Een uitgeschakelde dimensie komt zo ook niet via
     * een oude opgeslagen waarde het formulier weer binnen.
     */
    private function laadKenmerken(SoaRegel $regel): void
    {
        $huidig = $regel->kenmerken();

        $this->kenmerken = collect(Maatregelkenmerken::dimensies())
            ->map(fn (array $dimensie, string $sleutel) => array_values(
                array_intersect($huidig[$sleutel] ?? [], Maatregelkenmerken::waarden($sleutel))
            ))
            ->all();
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
        $this->bewerkteRegelId = null;
        $this->bewijsVoor = null;
    }

    /**
     * Terug naar de meegeleverde uitgangsclassificatie: `kenmerken_eigen` weer
     * op `null`. Bewust een eigen actie en geen "leeg opslaan" — leeg opslaan zou
     * een lege vaststelling zijn, en dat is iets anders dan geen vaststelling.
     */
    public function terugNaarUitgangspunt(): void
    {
        $this->vereisMuteren();

        $regel = $this->bewerkteRegel;

        abort_if($regel === null, 404);

        $regel->update(['kenmerken_eigen' => null]);

        $this->laadKenmerken($regel->refresh());
        $this->resetValidation();

        session()->flash('melding', 'Classificatie teruggezet naar het meegeleverde uitgangspunt.');
    }

    /**
     * Validatieregels voor de classificatie, afgeleid uit het schema.
     *
     * Elke actieve dimensie moet minstens één waarde hebben — een lege dimensie
     * is geen vaststelling maar een half ingevuld formulier — en elke waarde moet
     * in het vocabulaire van zijn eigen dimensie zitten.
     *
     * De regel op `kenmerken` zelf sluit dimensies buiten het schema uit. Zonder
     * die regel zou een uitgeschakelde dimensie (`capaciteiten`) alsnog binnen
     * kunnen komen, ook al staat hij nergens in het formulier.
     *
     * @return array<string, mixed>
     */
    private function kenmerkRegels(): array
    {
        $dimensies = Maatregelkenmerken::dimensies();
        $toegestaan = array_keys($dimensies);

        $regels = ['kenmerken' => ['array', function (string $attribuut, mixed $waarde, callable $fout) use ($dimensies, $toegestaan) {
            $waarde = (array) $waarde;
            $onbekend = array_diff(array_keys($waarde), $toegestaan);

            if ($onbekend !== []) {
                $fout('Onbekende of uitgeschakelde dimensie: '.implode(', ', $onbekend).'.');

                return;
            }

            $leeg = array_filter($toegestaan, fn (string $s) => empty($waarde[$s]));

            // Alles of niets. Helemaal leeg is geldig — dan stelt de organisatie
            // niets vast en blijft het uitgangspunt staan. Gedeeltelijk gevuld is
            // geen vaststelling maar een half ingevuld formulier.
            if ($leeg !== [] && count($leeg) !== count($toegestaan)) {
                $fout('Vul alle dimensies in, of laat ze allemaal leeg. Nog leeg: '
                    .implode(', ', array_map(fn (string $s) => strtolower($dimensies[$s]['label']), $leeg)).'.');
            }
        }]];

        foreach ($toegestaan as $sleutel) {
            $regels["kenmerken.{$sleutel}"] = ['array'];
            $regels["kenmerken.{$sleutel}.*"] = [Rule::in(Maatregelkenmerken::waarden($sleutel))];
        }

        return $regels;
    }

    /** @return array<string, string> */
    private function kenmerkLabels(): array
    {
        $labels = [];

        foreach (Maatregelkenmerken::dimensies() as $sleutel => $dimensie) {
            $labels["kenmerken.{$sleutel}"] = strtolower($dimensie['label']);
        }

        return $labels;
    }

    /**
     * De vast te leggen classificatie: alleen actieve dimensies, waarden ontdaan
     * van dubbelingen en op schemavolgorde. Zo staat er in de database geen
     * afspiegeling van de aanvinkvolgorde in het formulier.
     *
     * `null` wanneer het formulier helemaal leeg is. Dat is geen vaststelling en
     * mag er dus ook geen worden; een bestaande eigen classificatie blijft in dat
     * geval staan (wissen gaat via `terugNaarUitgangspunt()`).
     *
     * @return array<string, list<string>>|null
     */
    private function vastgesteldeKenmerken(): ?array
    {
        $vastgesteld = [];

        foreach (Maatregelkenmerken::dimensies() as $sleutel => $dimensie) {
            $vocabulaire = Maatregelkenmerken::waarden($sleutel);
            $gekozen = array_unique((array) ($this->kenmerken[$sleutel] ?? []));

            $vastgesteld[$sleutel] = array_values(array_filter(
                $vocabulaire,
                fn (string $waarde) => in_array($waarde, $gekozen, true),
            ));
        }

        return array_filter($vastgesteld) === [] ? null : $vastgesteld;
    }

    public function opslaan(): void
    {
        $this->vereisMuteren();

        $this->validate([
            // 'present', niet 'required': '' is hier een geldige waarde
            // (onbeslist), en die zou een required-regel juist afkeuren.
            'vanToepassing' => ['present', Rule::in(['', '0', '1'])],
            // De kolom is nullable omdat "onbeslist" juist géén motivatie hoeft
            // te hebben; zodra er wél een beslissing ligt, eist §4.3/6.1.3 van de
            // norm een onderbouwing. Vandaar de conditionele regel hier.
            'motivatie' => [Rule::requiredIf($this->vanToepassing !== ''), 'nullable', 'string'],
            // Vrije, korte verwijzingen — altijd optioneel.
            'beleidreferentie' => ['nullable', 'string', 'max:255'],
            'procesreferentie' => ['nullable', 'string', 'max:255'],
            'implementatiestatus' => ['required', Rule::in(self::STATUSSEN)],
            ...$this->kenmerkRegels(),
            ...$this->beoordelingRegels(),
        ], attributes: [
            'vanToepassing' => 'van toepassing',
            'motivatie' => 'motivatie',
            'beleidreferentie' => 'beleidreferentie',
            'procesreferentie' => 'procesreferentie',
            'implementatiestatus' => 'implementatiestatus',
            ...$this->kenmerkLabels(),
        ]);

        $beslist = $this->vanToepassing !== '';

        $attributen = [
            'van_toepassing' => $beslist ? (bool) $this->vanToepassing : null,
            'motivatie' => $this->motivatie ?: null,
            'beleidreferentie' => $this->beleidreferentie ?: null,
            'procesreferentie' => $this->procesreferentie ?: null,
            'implementatiestatus' => $this->implementatiestatus,
            // Alleen een echte beoordeling zet de datum; terugzetten naar
            // onbeslist wist hem weer, anders liegt de kolom.
            'laatst_beoordeeld_op' => $beslist ? now() : null,
        ];

        // Opslaan legt de complete set vast, ook als er niets is gewijzigd: dat
        // is de expliciete vaststelling ("wij hebben hiernaar gekeken"), en
        // precies wat een auditor wil kunnen zien. Een leeg formulier stelt niets
        // vast en raakt de kolom dus niet aan; wissen gaat via
        // `terugNaarUitgangspunt()`.
        if (($kenmerken = $this->vastgesteldeKenmerken()) !== null) {
            $attributen['kenmerken_eigen'] = $kenmerken;
        }

        $regel = $this->bewerkteRegel;
        $regel->update($attributen);

        $bijgewerkt = $this->slaBeoordelingenOp($regel);

        $this->sluitFormulier();
        session()->flash('melding', 'SoA-regel bijgewerkt.'
            .($bijgewerkt > 0 ? " {$bijgewerkt} BIO-verplichting(en) beoordeeld." : ''));
    }

    /**
     * Validatie van het BIO-blok.
     *
     * `motivatie` is hard verplicht bij `niet_belegd` en `niet_van_toepassing`: bij
     * `belegd` is het bewijsstuk de onderbouwing, bij deze twee is er niets anders
     * dan de tekst van de CISO.
     *
     * De verwijzing naar de risicoanalyse is dat níét, en dat is een bewuste keuze.
     * Deel 1 §7 van de BIO vraagt hem wel, maar de koppeling tussen een
     * risicobehandeling en een control wordt vanuit de risicokant gelegd — een harde
     * eis hier zou de CISO klemzetten in een formulier waarin hij het gat niet kán
     * dichten. In plaats daarvan is de afwezigheid een teller op deze pagina en een
     * regel in de export, net als `SoaRegel::mistBeleid()`.
     *
     * @return array<string, mixed>
     */
    private function beoordelingRegels(): array
    {
        return ['beoordelingen' => ['array', function (string $attribuut, mixed $waarde, callable $fout) {
            $nummers = $this->beoordeelbareNummers();

            foreach ((array) $waarde as $id => $invoer) {
                $nummer = $nummers[$id] ?? null;

                // Een id dat niet bij deze SoA-regel hoort: geen validatiefout maar
                // een poging tot iets anders. `slaBeoordelingenOp()` slaat hem
                // over; hier alleen niet verder valideren.
                if ($nummer === null) {
                    continue;
                }

                $status = $invoer['status'] ?? '';

                if (! array_key_exists($status, OverheidsmaatregelBeoordeling::STATUS_LABELS)) {
                    $fout("Onbekende status bij overheidsmaatregel {$nummer}.");

                    continue;
                }

                if (in_array($status, OverheidsmaatregelBeoordeling::MOTIVATIE_VERPLICHT, true)
                    && trim((string) ($invoer['motivatie'] ?? '')) === '') {
                    $fout("Overheidsmaatregel {$nummer}: '"
                        .mb_strtolower(OverheidsmaatregelBeoordeling::STATUS_LABELS[$status])
                        ."' vraagt een onderbouwing.");
                }

                // Vrije verwijzingen, dus nooit verplicht — alleen de kolomlengte
                // bewaken. Met het nummer erbij, want bij zeven verplichtingen
                // onder één maatregel is "beleidreferentie is te lang" geen
                // bruikbare melding.
                foreach (['beleidreferentie' => 'Beleidreferentie',
                    'procesreferentie' => 'Procesreferentie'] as $veld => $label) {
                    if (mb_strlen((string) ($invoer[$veld] ?? '')) > 255) {
                        $fout("Overheidsmaatregel {$nummer}: {$label} mag hoogstens 255 tekens zijn.");
                    }
                }
            }
        }]];
    }

    /**
     * De beoordelingen die bij de bewerkte regel horen: id => nummer.
     *
     * Dit is de autorisatiegrens van het BIO-blok. Het formulier stuurt id's mee en
     * die komen van de client; zonder deze filter zou een aangepast verzoek een
     * beoordeling onder een héél andere beheersmaatregel kunnen bijwerken.
     *
     * @return array<int, string>
     */
    private function beoordeelbareNummers(): array
    {
        $regel = $this->bewerkteRegel;

        if ($regel === null || ! Normprofiel::heeft('overheidsmaatregelen')) {
            return [];
        }

        return $regel->overheidsmaatregelBeoordelingen
            ->mapWithKeys(fn (OverheidsmaatregelBeoordeling $b) => [
                $b->id => $b->overheidsmaatregel?->nummer ?? '?',
            ])
            ->all();
    }

    /**
     * Legt de BIO-beoordelingen vast en geeft terug hoeveel er werkelijk wijzigden.
     *
     * Alleen bij een echte wijziging wordt `laatst_beoordeeld_op` gezet. Dat is het
     * verschil met de SoA-regel hierboven, die bij elke opslag de datum bijwerkt als
     * expliciete vaststelling: hier zou dat zeven data bijwerken omdat iemand de
     * motivatie van de bovenliggende maatregel aanpaste. De datum moet "wanneer is
     * naar déze verplichting gekeken" betekenen, niet "wanneer is deze modal
     * opgeslagen" — anders verjaart er nooit iets en is de teller waardeloos.
     */
    private function slaBeoordelingenOp(SoaRegel $regel): int
    {
        if ($this->beoordelingen === [] || ! Normprofiel::heeft('overheidsmaatregelen')) {
            return 0;
        }

        $toegestaan = $regel->overheidsmaatregelBeoordelingen->keyBy('id');
        $behandelingen = $regel->risicobehandelingen->pluck('id');
        $bijgewerkt = 0;

        foreach ($this->beoordelingen as $id => $invoer) {
            $beoordeling = $toegestaan->get((int) $id);

            if ($beoordeling === null) {
                continue;
            }

            $status = $invoer['status'] ?? $beoordeling->status;
            $motivatie = trim((string) ($invoer['motivatie'] ?? '')) ?: null;
            $beleid = trim((string) ($invoer['beleidreferentie'] ?? '')) ?: null;
            $proces = trim((string) ($invoer['procesreferentie'] ?? '')) ?: null;
            $behandelingId = (int) ($invoer['risicobehandeling_id'] ?? 0) ?: null;

            // Alleen een behandeling die aan déze control hangt. Een verwijzing
            // naar een risicoanalyse over iets anders is erger dan geen
            // verwijzing: die ziet eruit als een onderbouwing.
            if ($behandelingId !== null && ! $behandelingen->contains($behandelingId)) {
                $behandelingId = null;
            }

            // Een uitzondering die geen uitzondering meer is, houdt geen
            // risicoanalyse over — die hoorde bij de uitzondering.
            if ($status !== 'niet_van_toepassing') {
                $behandelingId = null;
            }

            // De referenties horen hier net zo goed in als de status. Zonder hen
            // wordt een gecorrigeerde vindplaats als "niets veranderd" gezien en
            // slaat de lus de rij helemaal over — het veld zou dan stil niet
            // opgeslagen worden.
            $ongewijzigd = $status === $beoordeling->status
                && $motivatie === $beoordeling->motivatie
                && $beleid === $beoordeling->beleidreferentie
                && $proces === $beoordeling->procesreferentie
                && $behandelingId === $beoordeling->risicobehandeling_id;

            if ($ongewijzigd) {
                continue;
            }

            $beoordeling->update([
                'status' => $status,
                'motivatie' => $motivatie,
                'beleidreferentie' => $beleid,
                'procesreferentie' => $proces,
                'risicobehandeling_id' => $behandelingId,
                // Terug naar onbeoordeeld wist de datum, anders liegt de kolom —
                // zelfde regel als bij `van_toepassing` hierboven.
                'laatst_beoordeeld_op' => $status === 'niet_beoordeeld' ? null : now(),
                'beoordeeld_door_id' => $status === 'niet_beoordeeld' ? null : auth()->id(),
            ]);

            $bijgewerkt++;
        }

        return $bijgewerkt;
    }

    /**
     * De rijen zoals het scherm ze toont, inclusief de actieve filters.
     *
     * Eén methode voor zowel `render()` als de schermkopie: die laatste mag geen
     * eigen query doen (implementatie/12h §6). Een kopieknop die zelf ophaalt,
     * is een lek dat er precies uitziet als een feature — hij zou een filter of
     * een record-beperking stilzwijgend kunnen overslaan.
     *
     * @return Collection<int, Maatregel>
     */
    private function gefilterdeMaatregelen(): Collection
    {
        return Maatregel::query()
            // Blok 5: alleen ACTIEF beleid telt als onderbouwing. Een concept
            // of ingetrokken document zou de dekking te rooskleurig maken.
            ->with(['soaRegel.beleidsdocumenten' => fn ($q) => $q->where('status', 'actief')])
            // Plan 04c: de restrisico-rollup per control leidt af uit de
            // gekoppelde behandelingen (max restrisico + distinct risico's).
            ->with('soaRegel.risicobehandelingen')
            // Alleen in een BIO-installatie: voor de kolom, de uitklapbare
            // regellaag en de bijlage bij de schermkopie. 93 maatregelen die elk
            // hun verplichtingen opzoeken is anders 93 queries — en zonder
            // `.overheidsmaatregel` erbij nog eens 118, want de nummers zitten
            // niet in de beoordeling maar in de referentiedata erachter.
            ->when(Normprofiel::heeft('overheidsmaatregelen'), fn ($q) => $q->with([
                'soaRegel.overheidsmaatregelBeoordelingen.overheidsmaatregel',
                'soaRegel.overheidsmaatregelBeoordelingen.bewijsKoppelingen',
            ]))
            ->when($this->filterThema !== '', fn ($q) => $q->where('thema', $this->filterThema))
            ->when($this->filterStatus !== '', fn ($q) => $q->whereHas(
                'soaRegel',
                fn ($sub) => $sub->where('implementatiestatus', $this->filterStatus)
            ))
            ->when($this->alleenOnbeslist, fn ($q) => $q->whereHas(
                'soaRegel',
                fn ($sub) => $sub->whereNull('van_toepassing')
            ))
            ->get()
            // Numeriek sorteren op '5.10' vs '5.9' vergt database-specifieke SQL
            // (SUBSTRING_INDEX/CAST); bij 93 rijen is sorteren in PHP simpeler
            // en werkt het op zowel MySQL als de sqlite-testdatabase.
            ->sortBy(function (Maatregel $maatregel) {
                [$hoofdstuk, $sub] = array_pad(explode('.', $maatregel->annex_a_referentie, 2), 2, '0');

                return (int) $hoofdstuk * 1000 + (int) $sub;
            })
            ->values();
    }

    public function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    protected function kopieBlok(): string
    {
        return 'risico-soa';
    }

    /**
     * De SoA als kopie voor de auditor (implementatie/12h).
     *
     * Naast de kolommen van het scherm staat de **motivatie** erin. Die staat op
     * het scherm een klik verderop, in de beoordelingsmodal, maar zonder die
     * kolom is dit geen SoA: "van toepassing: nee" zonder het waarom is precies
     * waar §6.1.3 d om vraagt en wat een auditor als eerste opslaat.
     *
     * In een BIO-installatie hangt er een bijlage onder met de verplichtingen
     * zelf (deelproducten/04c §5). Tot 17-08-2026 was dat één kolom met "3 / 7"
     * en geen enkel nummer — een document waarin de vraag "laat 5.24.03 zien"
     * onbeantwoordbaar is.
     */
    protected function schermkopie(): Schermkopie
    {
        $maatregelen = $this->gefilterdeMaatregelen();
        $metBio = Normprofiel::heeft('overheidsmaatregelen');

        return new Schermkopie(
            scherm: 'Statement of Applicability',
            kolommen: [
                'Ref.', 'Naam', 'Van toepassing', 'Motivatie',
                'Implementatiestatus', 'Restrisico', 'Beleid', 'Laatst beoordeeld',
                // Alleen in een BIO-installatie. Een lege kolom in elk ander
                // profiel zou de auditor laten zoeken naar iets wat er niet is.
                ...($metBio ? ['Verplichtingen'] : []),
            ],
            rijen: $maatregelen->map(fn (Maatregel $maatregel) => [
                'A.'.$maatregel->annex_a_referentie,
                $maatregel->naam,
                $this->toepasselijkheidLabel($maatregel->soaRegel),
                $maatregel->soaRegel?->motivatie,
                $this->statusLabel($maatregel->soaRegel?->implementatiestatus ?? 'niet_gestart'),
                $this->restrisicoLabel($maatregel->soaRegel),
                $this->beleidLabel($maatregel->soaRegel),
                $maatregel->soaRegel?->laatst_beoordeeld_op?->format('d-m-Y'),
                ...($metBio ? [$this->bioLabel($maatregel->soaRegel)] : []),
            ])->all(),
            bijlage: $metBio ? $this->verplichtingenbijlage($maatregelen) : null,
            // Het hele register, ook als er gefilterd is: dát verschil is nu juist
            // wat de kop van het document moet noemen (§4).
            totaalRijen: Maatregel::count(),
            filters: $this->actieveFilters(),
            toelichting: 'De beheersmaatregelen uit '.Normprofiel::label('bijlage').' van '
                .Normprofiel::label('naam').', met per maatregel de '
                .'toepasselijkheid, de onderbouwing en de implementatiestatus. Restrisico is het '
                .'hoogste netto-restrisico van de gekoppelde risico\'s, met tussen haakjes het aantal.',
        );
    }

    /**
     * De BIO-verplichtingen als eigen regels onder de SoA-tabel (04c §5).
     *
     * Put uit dezelfde verzameling als de hoofdtabel — geen tweede query, dat is
     * de regel uit 12h §6. De uitklapstand van het scherm telt hier bewust niet
     * mee: een auditdocument dat afhangt van welke rijen iemand toevallig had
     * opengeklikt, is niet reproduceerbaar.
     *
     * **Zonder normtekst**, en dat is geen omissie. Hij is er lang niet altijd
     * (alleen als de installatie de BIO zelf heeft ingelezen), hij loopt op tot
     * ruim 1400 tekens en is in een tabelcel onleesbaar, en hij staat onder
     * CC BY-NC-SA 4.0. Het nummer is waar een auditrapport naar verwijst; de
     * auditor heeft de norm zelf.
     *
     * @param  Collection<int, Maatregel>  $maatregelen
     */
    private function verplichtingenbijlage(Collection $maatregelen): Schermkopiebijlage
    {
        $rijen = [];
        $dragers = 0;

        foreach ($maatregelen as $maatregel) {
            $beoordelingen = $maatregel->soaRegel?->overheidsmaatregelBeoordelingen;

            if ($beoordelingen === null || $beoordelingen->isEmpty()) {
                continue;
            }

            $dragers++;

            foreach ($beoordelingen as $beoordeling) {
                $rijen[] = [
                    $beoordeling->overheidsmaatregel?->nummer,
                    'A.'.$maatregel->annex_a_referentie,
                    $beoordeling->statusLabel(),
                    $beoordeling->overheidsmaatregel?->cbw_reikwijdte
                        ? 'Cyberbeveiligingswet'
                        : 'Buiten Cbw — zelfregulering',
                    $beoordeling->motivatie,
                    $beoordeling->beleidreferentie,
                    $beoordeling->procesreferentie,
                    $beoordeling->bewijsKoppelingen->count(),
                    $beoordeling->laatst_beoordeeld_op?->format('d-m-Y'),
                ];
            }
        }

        return new Schermkopiebijlage(
            titel: 'Overheidsmaatregelen ('.Normprofiel::label('naam_kort').')',
            kolommen: [
                'Nummer', 'Beheersmaatregel', 'Status', 'Reikwijdte', 'Onderbouwing',
                'Beleidreferentie', 'Procesreferentie', 'Bewijs', 'Laatst beoordeeld',
            ],
            rijen: $rijen,
            // Deze drie zinnen zijn geen inleiding maar context: zonder hen is een
            // status "niet belegd" een cijfer zonder gewicht.
            toelichting: 'De verplichte minimale invulling van de beheersmaatregelen hierboven. '
                .'Deze verplichtingen kunnen niet op grond van een risico-inschatting worden '
                .'geaccepteerd — alleen als ze niet van toepassing kúnnen zijn, en dan met een '
                .'onderbouwende risicoanalyse. De tekst van de verplichtingen staat hier niet in; '
                .'die is auteursrechtelijk beschermd en staat in de norm zelf.',
            omvangregel: count($rijen).' verplichtingen bij '.$dragers.' van de '
                .$maatregelen->count().' getoonde beheersmaatregelen.',
        );
    }

    /** @return array<string, string> */
    private function actieveFilters(): array
    {
        $filters = [];

        if ($this->filterThema !== '') {
            $filters['Thema'] = $this->filterThema;
        }

        if ($this->filterStatus !== '') {
            $filters['Implementatiestatus'] = mb_strtolower($this->statusLabel($this->filterStatus));
        }

        if ($this->alleenOnbeslist) {
            $filters['Alleen onbesliste regels'] = 'ja';
        }

        return $filters;
    }

    private function toepasselijkheidLabel(?SoaRegel $regel): string
    {
        if ($regel === null || $regel->isOnbeslist()) {
            return 'Onbeslist';
        }

        return $regel->van_toepassing ? 'Ja' : 'Nee';
    }

    private function restrisicoLabel(?SoaRegel $regel): string
    {
        $aantal = $regel?->aantalGekoppeldeRisicos() ?? 0;

        if ($aantal === 0) {
            return '—';
        }

        $piek = $regel?->piekRestrisico();

        return $piek === null ? 'onbepaald' : $piek.' ('.$aantal.')';
    }

    /**
     * "3 / 5" — hoeveel BIO-verplichtingen onder deze maatregel belegd zijn.
     *
     * Uitzonderingen tellen niet mee in de noemer: een verplichting die niet van
     * toepassing kán zijn, hoort niet als achterstand te lezen. Maar dan moet hun
     * aantal er wél bij staan, anders is de noemer niet te herleiden: zijn er tien
     * verplichtingen waarvan er drie zijn uitgezonderd, dan leest "3 / 7" als
     * volledig terwijl er tien in de norm staan.
     *
     * Een streepje bij een maatregel zonder verplichtingen — dat is de norm die
     * daar niets voorschrijft, en geen gat. Zie {@see bioTitel()} voor wat daar
     * dan wél geldt.
     */
    public function bioLabel(?SoaRegel $regel): string
    {
        $beoordelingen = $regel?->overheidsmaatregelBeoordelingen;

        if ($beoordelingen === null || $beoordelingen->isEmpty()) {
            return '—';
        }

        $uitgezonderd = $beoordelingen->where('status', 'niet_van_toepassing')->count();
        $noemer = $beoordelingen->count() - $uitgezonderd;
        $belegd = $beoordelingen->where('status', 'belegd')->count();

        if ($noemer === 0) {
            return 'alle '.$uitgezonderd.' uitgezonderd';
        }

        return "{$belegd} / {$noemer}"
            .($uitgezonderd > 0 ? " · {$uitgezonderd} uitgezonderd" : '');
    }

    /**
     * De toelichting bij een lege cel: 39 van de 93 beheersmaatregelen dragen
     * geen overheidsmaatregel, en daar schrijft de BIO een andere route voor
     * (deelproducten/04b §3.3). Zwijgen leest als "hier is niets te doen".
     */
    public function bioTitel(?SoaRegel $regel): string
    {
        if ($regel?->overheidsmaatregelBeoordelingen->isNotEmpty()) {
            return 'Belegde verplichtingen van het aantal dat van toepassing is.';
        }

        return 'Geen overheidsmaatregelen onder deze beheersmaatregel. De BIO vraagt hier om '
            .'de implementatierichtlijn uit NEN-EN-ISO/IEC 27002; afwijken of niet toepassen '
            .'wordt onderbouwd met een risicoanalyse, met de verwijzing in de bijlage '
            .'Uitzonderingen van de VvT.';
    }

    private function beleidLabel(?SoaRegel $regel): string
    {
        if ($regel?->mistBeleid()) {
            return 'Geen beleid';
        }

        return $regel?->beleidsdocumenten->pluck('titel')->implode(', ') ?: '—';
    }

    public function render()
    {
        $maatregelen = $this->gefilterdeMaatregelen();

        // Voortgangsteller: het gap-signaal uit §2, samengevat boven de tabel.
        $alle = SoaRegel::query();

        return view('livewire.soa-overzicht', [
            'perThema' => $maatregelen->groupBy('thema'),
            'themas' => self::THEMAS,
            'kenmerkDimensies' => $dimensies = Maatregelkenmerken::dimensies(),
            // Het vocabulaire per dimensie, één keer opgehaald: `waarden()` kan
            // een bronbestand lezen en dat wil je niet per checkbox doen.
            'kenmerkWaarden' => collect($dimensies)
                ->map(fn (array $dimensie, string $sleutel) => Maatregelkenmerken::waarden($sleutel))
                ->all(),
            'totaal' => (clone $alle)->count(),
            'onbeslist' => (clone $alle)->whereNull('van_toepassing')->count(),
            'vanToepassingJa' => (clone $alle)->where('van_toepassing', true)->count(),
            'geimplementeerd' => (clone $alle)
                ->where('van_toepassing', true)
                ->where('implementatiestatus', 'geimplementeerd')
                ->count(),
            // De BIO-tellers en de balansweergave. Beide zijn no-ops buiten een
            // BIO-installatie: `Overheidsmaatregeldekking` geeft dan nullen terug
            // en de weergave hangt aan `heeftOverheidsmaatregelen()`.
            'biodekking' => Overheidsmaatregeldekking::huidige(),
            'balans' => Normprofiel::heeft('overheidsmaatregelen') ? Maatregelbalans::huidige() : null,
            'beoordelingStatussen' => OverheidsmaatregelBeoordeling::STATUS_LABELS,
            // De kolom en de regellaag hangen aan de capaciteit en niet aan de
            // profielnaam; blade vergelijkt hier nooit zelf op 'bio2'.
            'toontVerplichtingen' => Normprofiel::heeft('overheidsmaatregelen'),
        ]);
    }
}
