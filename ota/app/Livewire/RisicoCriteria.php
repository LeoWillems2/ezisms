<?php

namespace App\Livewire;

use App\Actions\ActiveerRisicocriteria;
use App\Models\Beleidsdocument;
use App\Models\Besluit;
use App\Models\Risico;
use App\Models\RisicocriteriaVersie;
use App\Support\Bandverschuiving;
use App\Support\Risicoverdeling;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * De risicocriteria (ISO 27001 §6.1.2 a) als vastgesteld kader
 * (implementatie/04g §6).
 *
 * Eén versie draagt het hele kader: de risk-appetite-verklaring, de rode
 * acceptatiedrempel, de amber-waarschuwingsgrens, de leidraad per as en de tien
 * niveaudefinities. Tot 04g was dit één rij die de CISO op elk moment kon
 * verzetten; sindsdien loopt een wijziging via concept → ter goedkeuring →
 * actief, met dezelfde statusgang als de scope-verklaring.
 *
 * **De CISO bewerkt, Management stelt vast**, en dat volgt uit de bestaande
 * rechtenladder zonder dat er iets aan de matrix hoeft te veranderen:
 * `goedkeuren` staat sinds 29-07-2026 buiten de ladder en impliceert alleen
 * `lezen`. Management heeft op `risico-soa` alleen dat niveau en kan dus wél
 * activeren en afwijzen, maar niets bewerken. Let op één afwijking van
 * `ScopeBeheer`: `terugNaarConcept()` staat hier op `goedkeuren` en niet op
 * `muteren`, want afwijzen is hier een handeling van de goedkeurder. De CISO
 * houdt zijn eigen weg terug via een nieuw concept.
 */
#[Layout('components.layouts.app')]
class RisicoCriteria extends Component
{
    /** Bovengrens bij een 5×5-matrix: score = kans × impact, max 25. */
    private const MAX_SCORE = Risicoverdeling::SCHAAL * Risicoverdeling::SCHAAL;

    // --- Bewerkbare velden van de werkversie (alleen actief zolang die 'concept' is).

    public string $omschrijving = '';

    public int $drempelwaardeScore = Risico::DREMPEL_STANDAARD;

    public int $waarschuwingsdrempelScore = Risico::WAARSCHUWINGSDREMPEL_STANDAARD;

    public string $leidraadKans = '';

    public string $leidraadImpact = '';

    public string $wijzigingsreden = '';

    public ?int $beleidsdocumentId = null;

    public ?int $besluitId = null;

    /**
     * De tien niveaudefinities, als `[as][niveau] => [naam, omschrijving,
     * kwantitatieve_band]`. Inline bewerkbaar en niet in een modal per niveau:
     * bij het formuleren van "wat is een 4" is juist de context van de andere
     * niveaus wat je nodig hebt.
     *
     * @var array<string, array<int, array<string, ?string>>>
     */
    public array $niveaus = [];

    public string $goedgekeurdDoor = '';

    // Id van de versie die read-only in de detail-modal wordt getoond.
    public ?int $bekekenVersieId = null;

    // Aparte boolean voor de zichtbaarheid: Flux zet de gebonden waarde bij
    // sluiten op `false`, wat niet op een nullable-int-property past.
    public bool $toontDetail = false;

    public function mount(): void
    {
        $this->laadWerkversieVelden();
    }

    // --- Autorisatie -------------------------------------------------------

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['risico-soa', 'muteren']);
    }

    /**
     * Vaststellen is geen bewerken: §6.1.2 a) legt de risicocriteria bij de
     * directie. De CISO stelt op en dient in, Management activeert.
     */
    public function magGoedkeuren(): bool
    {
        return Gate::allows('heeft-niveau', ['risico-soa', 'goedkeuren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    private function vereisGoedkeuren(): void
    {
        abort_unless($this->magGoedkeuren(), 403);
    }

    // --- De versies --------------------------------------------------------

    public function getActieveVersieProperty(): ?RisicocriteriaVersie
    {
        return RisicocriteriaVersie::with(['niveaus', 'beleidsdocument.actieveVersie', 'besluit.reviewsessie'])
            ->where('status', 'actief')
            ->first();
    }

    /** De versie waaraan gewerkt wordt: 'concept' of 'ter_goedkeuring' (max. één). */
    public function getWerkVersieProperty(): ?RisicocriteriaVersie
    {
        return RisicocriteriaVersie::with('niveaus')
            ->whereIn('status', ['concept', 'ter_goedkeuring'])
            ->latest('versienummer')
            ->first();
    }

    public function getBekekenVersieProperty(): ?RisicocriteriaVersie
    {
        if ($this->bekekenVersieId === null) {
            return null;
        }

        return RisicocriteriaVersie::with(['niveaus', 'beleidsdocument', 'besluit'])
            ->find($this->bekekenVersieId);
    }

    /**
     * Wat er met het register gebeurt als de werkversie wordt geactiveerd.
     *
     * Rekent met de waarden zoals ze op dit moment in het formulier staan, zodat
     * de CISO het effect al ziet terwijl hij de drempel verzet — en Management
     * het bij de goedkeuring nog eens onder ogen krijgt.
     */
    public function getVerschuivingProperty(): ?Bandverschuiving
    {
        if ($this->werkVersie === null) {
            return null;
        }

        return Bandverschuiving::tussen(
            Risico::drempelwaarde(),
            Risico::waarschuwingsdrempel(),
            $this->drempelwaardeScore,
            $this->waarschuwingsdrempelScore,
        );
    }

    public function bekijkVersie(int $versieId): void
    {
        // Alleen-lezen inzage; de route dekt het leesrecht al af.
        $this->bekekenVersieId = $versieId;
        $this->toontDetail = true;
    }

    public function sluitBekekenVersie(): void
    {
        $this->toontDetail = false;
        $this->bekekenVersieId = null;
    }

    private function laadWerkversieVelden(): void
    {
        $werk = $this->werkVersie;

        if ($werk === null) {
            $this->reset([
                'omschrijving', 'drempelwaardeScore', 'waarschuwingsdrempelScore',
                'leidraadKans', 'leidraadImpact', 'wijzigingsreden',
                'beleidsdocumentId', 'besluitId', 'niveaus',
            ]);

            // Zonder werkversie tonen de bandenvoorvertoning en de verschuiving
            // het huidige kader; anders zou het scherm een verschuiving melden
            // ten opzichte van de fallbackconstanten.
            $this->drempelwaardeScore = Risico::drempelwaarde();
            $this->waarschuwingsdrempelScore = Risico::waarschuwingsdrempel();

            return;
        }

        $this->omschrijving = $werk->omschrijving;
        $this->drempelwaardeScore = $werk->drempelwaarde_score;
        $this->waarschuwingsdrempelScore = $werk->waarschuwingsdrempel_score;
        $this->leidraadKans = $werk->leidraad_kans;
        $this->leidraadImpact = $werk->leidraad_impact;
        $this->wijzigingsreden = $werk->wijzigingsreden ?? '';
        $this->beleidsdocumentId = $werk->beleidsdocument_id;
        $this->besluitId = $werk->besluit_id;

        $this->niveaus = [];

        foreach (['kans', 'impact'] as $as) {
            foreach ($werk->niveausVan($as) as $niveau => $definitie) {
                $this->niveaus[$as][$niveau] = [
                    'naam' => $definitie->naam,
                    'omschrijving' => $definitie->omschrijving,
                    'kwantitatieve_band' => $definitie->kwantitatieve_band,
                ];
            }
        }
    }

    // --- Opstellen en indienen (CISO) --------------------------------------

    /**
     * Een nieuwe conceptversie is een volledige kopie van de actieve versie,
     * inclusief de tien niveaus. De CISO past daarna aan wat wijzigt, in plaats
     * van het hele kader opnieuw te moeten formuleren.
     */
    public function nieuweConceptversieStarten(): void
    {
        $this->vereisMuteren();

        if ($this->werkVersie !== null) {
            return; // maximaal één werkversie tegelijk
        }

        $vorige = RisicocriteriaVersie::with('niveaus')->where('status', 'actief')->firstOrFail();

        $nieuw = RisicocriteriaVersie::create([
            'versienummer' => $vorige->versienummer + 1,
            'status' => 'concept',
            'omschrijving' => $vorige->omschrijving,
            'drempelwaarde_score' => $vorige->drempelwaarde_score,
            'waarschuwingsdrempel_score' => $vorige->waarschuwingsdrempel_score,
            'leidraad_kans' => $vorige->leidraad_kans,
            'leidraad_impact' => $vorige->leidraad_impact,
            'beleidsdocument_id' => $vorige->beleidsdocument_id,
            // `besluit_id` bewust NIET overgenomen: het besluit hoort bij dít
            // wijzigingsmoment. Meekopiëren zou een nieuwe versie laten leunen op
            // de vergadering waarin de vorige werd vastgesteld.
        ]);

        foreach ($vorige->niveaus as $niveau) {
            $nieuw->niveaus()->create($niveau->only(['as', 'niveau', 'naam', 'omschrijving', 'kwantitatieve_band']));
        }

        $this->laadWerkversieVelden();
    }

    public function conceptOpslaan(): void
    {
        $this->vereisMuteren();

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->isBewerkbaar(), 422);

        $gevalideerd = $this->validate($this->regels(), attributes: $this->veldnamen());

        $werk->update([
            'omschrijving' => $gevalideerd['omschrijving'],
            'drempelwaarde_score' => $gevalideerd['drempelwaardeScore'],
            'waarschuwingsdrempel_score' => $gevalideerd['waarschuwingsdrempelScore'],
            'leidraad_kans' => $gevalideerd['leidraadKans'],
            'leidraad_impact' => $gevalideerd['leidraadImpact'],
            'wijzigingsreden' => $this->wijzigingsreden ?: null,
            'beleidsdocument_id' => $this->beleidsdocumentId,
            'besluit_id' => $this->besluitId,
        ]);

        foreach ($this->niveaus as $as => $niveaus) {
            foreach ($niveaus as $niveau => $inhoud) {
                $werk->niveaus()->where('as', $as)->where('niveau', $niveau)->first()?->update([
                    'naam' => $inhoud['naam'],
                    'omschrijving' => $inhoud['omschrijving'],
                    'kwantitatieve_band' => ($inhoud['kwantitatieve_band'] ?? '') !== ''
                        ? $inhoud['kwantitatieve_band']
                        : null,
                ]);
            }
        }

        session()->flash('melding', 'Conceptversie opgeslagen.');
    }

    public function indienenTerGoedkeuring(): void
    {
        $this->vereisMuteren();

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->isBewerkbaar(), 422);

        $this->validate($this->regels(), attributes: $this->veldnamen());

        // De schaal moet compleet zijn vóór vaststelling: een ontbrekend niveau
        // verdwijnt stil uit de keuzelijst en dan scoort niemand het nog.
        foreach (['kans', 'impact'] as $as) {
            if (array_keys($werk->niveausVan($as)->all()) !== range(1, Risicoverdeling::SCHAAL)) {
                session()->flash('fout', "De {$as}-schaal is niet compleet; er horen "
                    .Risicoverdeling::SCHAAL.' niveaus in te staan.');

                return;
            }
        }

        $werk->update(['status' => 'ter_goedkeuring']);
        session()->flash('melding', 'Versie ingediend ter goedkeuring.');
    }

    // --- Vaststellen (Management) ------------------------------------------

    public function terugNaarConcept(): void
    {
        $this->vereisGoedkeuren();

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->status === 'ter_goedkeuring', 422);

        $werk->update(['status' => 'concept']);
        $this->laadWerkversieVelden();
        session()->flash('melding', 'Versie teruggezet naar concept.');
    }

    public function activeren(): void
    {
        $this->vereisGoedkeuren();

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->status === 'ter_goedkeuring', 422);

        $this->validate([
            'goedgekeurdDoor' => ['required', 'string', 'max:255'],
        ], attributes: ['goedgekeurdDoor' => 'goedgekeurd door']);

        $verschuiving = app(ActiveerRisicocriteria::class)($werk, $this->goedgekeurdDoor);

        $this->reset('goedgekeurdDoor');
        $this->laadWerkversieVelden();

        $omhoog = $verschuiving->omhoog()->count();
        session()->flash('melding', $omhoog === 0
            ? 'De nieuwe risicocriteria zijn vastgesteld.'
            : "De nieuwe risicocriteria zijn vastgesteld. {$omhoog} risico('s) wegen nu zwaarder en "
                .'kregen een herbeoordelingstaak.');
    }

    // --- Validatie ---------------------------------------------------------

    /** @return array<string, list<string>> */
    private function regels(): array
    {
        return [
            'omschrijving' => ['required', 'string', 'max:2000'],
            'drempelwaardeScore' => ['required', 'integer', 'min:1', 'max:'.self::MAX_SCORE],
            // De amber-grens ligt op of onder de rode drempel, anders is er geen
            // amber-band (en zou amber boven rood komen).
            'waarschuwingsdrempelScore' => ['required', 'integer', 'min:1', 'lte:drempelwaardeScore'],
            'leidraadKans' => ['required', 'string', 'max:2000'],
            'leidraadImpact' => ['required', 'string', 'max:2000'],
            'wijzigingsreden' => ['nullable', 'string', 'max:2000'],
            'beleidsdocumentId' => ['nullable', 'integer', 'exists:beleidsdocumenten,id'],
            'besluitId' => ['nullable', 'integer', 'exists:besluiten,id'],
            'niveaus.*.*.naam' => ['required', 'string', 'max:255'],
            'niveaus.*.*.omschrijving' => ['required', 'string', 'max:2000'],
            // Optioneel, en dat blijft het: het ISMS levert geen omzetpercentage
            // mee en gaat er ook niet om vragen (04g §2.3).
            'niveaus.*.*.kwantitatieve_band' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    private function veldnamen(): array
    {
        return [
            'omschrijving' => 'risk-appetite-verklaring',
            'drempelwaardeScore' => 'acceptatiedrempel',
            'waarschuwingsdrempelScore' => 'waarschuwingsgrens',
            'leidraadKans' => 'leidraad kans',
            'leidraadImpact' => 'leidraad impact',
            'niveaus.*.*.naam' => 'naam van het niveau',
            'niveaus.*.*.omschrijving' => 'omschrijving van het niveau',
            'niveaus.*.*.kwantitatieve_band' => 'kwantitatieve band',
        ];
    }

    public function render()
    {
        return view('livewire.risico-criteria', [
            'historie' => RisicocriteriaVersie::where('status', 'vervangen')
                ->orderByDesc('versienummer')
                ->get(),
            'beleidsdocumenten' => Beleidsdocument::where('type', 'beleid')->orderBy('titel')->get(),
            'besluiten' => Besluit::with('reviewsessie')->orderByDesc('id')->limit(50)->get(),
        ]);
    }
}
