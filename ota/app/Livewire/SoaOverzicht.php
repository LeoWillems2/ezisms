<?php

namespace App\Livewire;

use App\Livewire\Concerns\LevertSchermkopie;
use App\Models\Maatregel;
use App\Models\SoaRegel;
use App\Support\Maatregelkenmerken;
use App\Support\Normprofiel;
use App\Support\Schermkopie;
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

    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['risico-soa', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['risico-soa', 'muteren']);
    }

    public function getBewerkteRegelProperty(): ?SoaRegel
    {
        return $this->bewerkteRegelId
            ? SoaRegel::with('maatregel')->find($this->bewerkteRegelId)
            : null;
    }

    public function bewerk(int $regelId): void
    {
        $this->vereisMuteren();

        $regel = SoaRegel::findOrFail($regelId);

        $this->bewerkteRegelId = $regel->id;
        $this->vanToepassing = $regel->van_toepassing === null ? '' : ($regel->van_toepassing ? '1' : '0');
        $this->motivatie = $regel->motivatie ?? '';
        $this->beleidreferentie = $regel->beleidreferentie ?? '';
        $this->procesreferentie = $regel->procesreferentie ?? '';
        $this->implementatiestatus = $regel->implementatiestatus;
        $this->laadKenmerken($regel);

        $this->resetValidation();
        $this->toontFormulier = true;
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

        $this->bewerkteRegel->update($attributen);

        $this->sluitFormulier();
        session()->flash('melding', 'SoA-regel bijgewerkt.');
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
     */
    protected function schermkopie(): Schermkopie
    {
        $maatregelen = $this->gefilterdeMaatregelen();

        return new Schermkopie(
            scherm: 'Statement of Applicability',
            kolommen: [
                'Ref.', 'Naam', 'Van toepassing', 'Motivatie',
                'Implementatiestatus', 'Restrisico', 'Beleid', 'Laatst beoordeeld',
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
            ])->all(),
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
        ]);
    }
}
