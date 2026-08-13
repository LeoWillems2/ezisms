<?php

namespace App\Livewire;

use App\Livewire\Concerns\LevertSchermkopie;
use App\Models\AuditLogregel;
use App\Models\Blok;
use App\Models\Gebruiker;
use App\Models\Ketencontrole;
use App\Support\Audittrailketen;
use App\Support\Recordscope;
use App\Support\Schermkopie;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only weergave van de append-only audit trail. Bewust geen enkele actie
 * die een logregel wijzigt of verwijdert (implementatie/06 §9).
 *
 * Als enige overzicht in het platform gepagineerd: dit is de enige tabel die
 * ongelimiteerd groeit.
 */
#[Layout('components.layouts.app')]
class AuditLogOverzicht extends Component
{
    use LevertSchermkopie, WithPagination;

    #[Url]
    public string $filterBlok = '';

    #[Url]
    public string $filterEntiteitType = '';

    #[Url]
    public string $filterGebruikerId = '';

    #[Url]
    public string $filterActie = '';

    #[Url]
    public string $vanaf = '';

    #[Url]
    public string $tot = '';

    /**
     * Naam => initialen, alleen opgebouwd als er een kopie wordt gemaakt.
     *
     * @var array<string, string>|null
     */
    private ?array $namenPerInitialen = null;

    /**
     * De route-middleware checkt `lezen`, maar dat is hier te ruim: Medewerker
     * heeft `uitvoeren` (eigen bewijs uploaden) en zou daarmee ieders handelen
     * kunnen doorlezen. Volledige inzage vereist `muteren` of `exporteren`.
     */
    public function mount(): void
    {
        abort_unless(Recordscope::magAllesZien('bewijsrepository-audit-trail'), 403);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /** Eén bron voor het scherm en voor de schermkopie (12h §6). */
    private function regelsQuery(): Builder
    {
        return AuditLogregel::query()
            ->with('gebruiker')
            ->when($this->filterBlok !== '', fn ($q) => $q->where('blok_naam', $this->filterBlok))
            ->when($this->filterEntiteitType !== '', fn ($q) => $q->where('entiteit_type', $this->filterEntiteitType))
            ->when($this->filterGebruikerId !== '', fn ($q) => $q->where('gebruiker_id', $this->filterGebruikerId))
            ->when($this->filterActie !== '', fn ($q) => $q->where('actie', $this->filterActie))
            ->when($this->vanaf !== '', fn ($q) => $q->where('tijdstip', '>=', $this->dagrand($this->vanaf)))
            ->when($this->tot !== '', fn ($q) => $q->where('tijdstip', '<=', $this->dagrand($this->tot, eindeVanDeDag: true)))
            ->orderByDesc('tijdstip')
            ->orderByDesc('id');
    }

    /**
     * De ingetypte datum is een lokale dag; de kolom staat in UTC (00o §4).
     *
     * Met `whereDate` op de ruwe kolom viel een regel die om 00:30 lokaal
     * gebeurde — 22:30 UTC de dag ervóór — buiten het filter "vanaf die dag",
     * terwijl hij op het scherm wél op die dag staat. Het scherm sprak zichzelf
     * dan tegen op precies de plek waar iemand telt.
     */
    private function dagrand(string $datum, bool $eindeVanDeDag = false): CarbonImmutable
    {
        $dag = CarbonImmutable::parse($datum, config('tijd.weergave'));

        return ($eindeVanDeDag ? $dag->endOfDay() : $dag->startOfDay())->utc();
    }

    private function gefilterdeRegels(): LengthAwarePaginator
    {
        return $this->regelsQuery()->paginate(50);
    }

    public function render()
    {
        $regels = $this->gefilterdeRegels();

        return view('livewire.audit-log-overzicht', [
            'regels' => $regels,
            // De ketenstatus hoort bovenaan dit scherm: het is de eerste vraag
            // die een auditor bij een audit trail stelt (implementatie/06c §6).
            'ketencontrole' => Ketencontrole::laatste(),
            'kophash' => Audittrailketen::kop(),
            'blokken' => Blok::orderBy('naam')->get(),
            'gebruikers' => Gebruiker::orderBy('naam')->get(),
            'entiteitTypes' => AuditLogregel::query()
                ->distinct()
                ->orderBy('entiteit_type')
                ->pluck('entiteit_type'),
        ]);
    }

    protected function kopieBlok(): string
    {
        return 'bewijsrepository-audit-trail';
    }

    /**
     * De kopie van dit scherm draagt de **kophash** (implementatie/06c §8).
     *
     * Dat is het punt waar de keten pas echt iets waard wordt: wie de database
     * kan wijzigen, kan de hele keten herberekenen — maar niet het document dat
     * de auditor heeft meegenomen. Eén vergelijking bij de volgende audit is dan
     * genoeg.
     *
     * Twee afwijkingen van de andere schermen (12h §13c):
     *
     * - **Namen staan er als initialen in.** Een trail noemt bij elke handeling
     *   wie hem deed, en dat gaat over gedrag van werknemers over maanden heen.
     *   Op het scherm hoort die naam te staan — daar zit de CISO naar te kijken
     *   met een reden — maar een document dat het pand verlaat, hoeft dat niet
     *   te herhalen. Dezelfde keuze als bij `isms:exporteer`.
     * - **Met een datumfilter gaat de hele periode mee**, niet de zichtbare
     *   vijftig. Wie een periode kiest, vraagt om die periode. De regel uit §6
     *   (rijen komen al opgehaald binnen) bestaat om een record-beperking niet
     *   stilzwijgend te omzeilen; dit scherm kent die beperking niet — het eist
     *   volledige inzage in `mount()` — dus die reden geldt hier niet.
     */
    protected function schermkopie(): Schermkopie
    {
        $periodeGekozen = $this->vanaf !== '' || $this->tot !== '';

        $regels = $periodeGekozen
            ? $this->regelsQuery()->get()
            : collect($this->gefilterdeRegels()->items());

        $controle = Ketencontrole::laatste();

        $keten = 'Kophash op het moment van deze kopie: '.(Audittrailketen::kop() ?? '—').'. ';
        $keten .= $controle === null
            ? 'De keten is nog niet gecontroleerd.'
            : $controle->samenvatting().', vastgesteld op '.$controle->tijdstip->lokaal()->format('d-m-Y H:i').'.';

        return new Schermkopie(
            scherm: 'Audit trail',
            kolommen: ['Tijdstip ('.now()->lokaal()->format('T').')', 'Gebruiker', 'Blok', 'Entiteit', 'Actie', 'Gewijzigde velden'],
            rijen: $regels->map(fn (AuditLogregel $regel) => [
                $regel->tijdstip->lokaal()->format('d-m-Y H:i:s'),
                $this->handelende($regel),
                $regel->blok_naam,
                $this->entiteit($regel).' ('.str_replace('_', ' ', $regel->entiteit_type)
                    .($regel->entiteit_id === null ? ', verzameling' : ' #'.$regel->entiteit_id).')',
                str_replace('_', ' ', $regel->actie),
                implode(', ', $regel->gewijzigdeVelden()),
            ])->all(),
            // Het hele logboek, niet het gefilterde deel: het verschil tussen de
            // twee is precies wat de kop van het document moet noemen (12h §4).
            totaalRijen: AuditLogregel::count(),
            filters: $this->actieveFilters(),
            toelichting: ($periodeGekozen
                ? 'Alle regels uit de gekozen periode, nieuwste eerst. '
                : 'De regels zoals ze op het scherm stonden, nieuwste eerst. ')
                .'Personen staan als initialen; de volledige namen staan op het scherm. '
                .$keten.' Bewaar dit document: die hash is buiten dit systeem het enige ijkpunt '
                .'waartegen een latere versie van de trail te vergelijken is.',
            // Initialen in plaats van namen: hetzelfde uitgangspunt als de
            // export. Het blijft herleidbaar binnen een kleine organisatie —
            // dit is pseudonimisering, geen anonimiteit — maar het document
            // hoeft de namen niet te herhalen om te tonen dát er toezicht is.
            metPersoonsgegevens: false,
        );
    }

    /**
     * Wie de handeling deed, als initialen.
     *
     * Uit `gebruiker_naam` en niet uit het account: die naam is de momentopname
     * uit de trail, en het account kan weg zijn. Zonder `gebruiker_id` is het
     * geen persoon maar het systeem — daar valt niets te anonimiseren en
     * "S(g" zou alleen maar verwarren.
     */
    private function handelende(AuditLogregel $regel): string
    {
        return $regel->gebruiker_id === null
            ? $regel->gebruiker_naam
            : Gebruiker::initialenVan($regel->gebruiker_naam);
    }

    /**
     * De omschrijving van de entiteit, met namen erin teruggebracht tot
     * initialen.
     *
     * Anonimiseren van de kolom "Gebruiker" alleen is niet genoeg: bij een
     * account ís de omschrijving een naam, en een leesbevestiging of
     * trainingsvoltooiing heet *"… door Ciske Willems"*. Dat zijn precies de
     * regels die over personen gaan.
     *
     * Vandaar twee wegen en geen slimmigheid: bij het entiteitstype `gebruiker`
     * is de omschrijving de naam, en verder wordt alleen vervangen wat als
     * volledige naam in het gebruikersbestand voorkomt. Namen raden in vrije
     * tekst zou de ene keer te veel wegpoetsen en de andere keer te weinig.
     *
     * **Wat dit niet vangt:** de naam van een account dat inmiddels is
     * verwijderd. Die staat niet meer in het bestand en blijft dus staan waar
     * hij in een omschrijving is meegeschreven.
     */
    private function entiteit(AuditLogregel $regel): string
    {
        $omschrijving = (string) $regel->entiteit_omschrijving;

        if ($regel->entiteit_type === 'gebruiker') {
            return Gebruiker::initialenVan($omschrijving);
        }

        $namen = $this->namenPerInitialen ??= Gebruiker::orderByDesc('naam')
            ->pluck('naam')
            ->mapWithKeys(fn (string $naam) => [$naam => Gebruiker::initialenVan($naam)])
            ->all();

        return strtr($omschrijving, $namen);
    }

    /** @return array<string, string> */
    private function actieveFilters(): array
    {
        $filters = [];

        if ($this->filterBlok !== '') {
            $filters['Blok'] = $this->filterBlok;
        }

        if ($this->filterEntiteitType !== '') {
            $filters['Entiteit'] = str_replace('_', ' ', $this->filterEntiteitType);
        }

        if ($this->filterGebruikerId !== '') {
            $filters['Gebruiker'] = (string) Gebruiker::find($this->filterGebruikerId)?->naam;
        }

        if ($this->filterActie !== '') {
            $filters['Actie'] = str_replace('_', ' ', $this->filterActie);
        }

        if ($this->vanaf !== '') {
            $filters['Vanaf'] = $this->vanaf;
        }

        if ($this->tot !== '') {
            $filters['Tot en met'] = $this->tot;
        }

        return $filters;
    }
}
