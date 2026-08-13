<?php

namespace App\Livewire;

use App\Models\Afwijking;
use App\Models\Asset;
use App\Models\Incident;
use App\Models\Risico;
use App\Support\Meldplicht;
use App\Support\Recordscope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class IncidentDetail extends Component
{
    public Incident $incident;

    public string $status = '';

    public string $assetId = '';

    public string $risicoId = '';

    /** Ingevuld bij het sluiten wanneer er geen afwijking uit is voortgekomen. */
    public string $geenAfwijkingReden = '';

    // ---- Externe meldplicht (implementatie/08b §4) ----

    /** Het wettelijke ankerpunt; corrigeerbaar, zie `beoordeelMeldplicht()`. */
    public string $kennisnameOp = '';

    // De twee raakvlakvragen. Ze volgen de twee wetten en bepalen samen of er
    // een documentatieplicht is; de grondslag volgt eruit, dus die wordt niet
    // apart gevraagd. '' is "nog niet beoordeeld" en géén "nee".
    public string $raaktPersoonsgegevens = '';

    public string $isNetwerkInformatieIncident = '';

    /** '', '1' of '0' — leeg is "nog niet beoordeeld" en géén "nee". */
    public string $externMeldingsplichtig = '';

    public string $meldplichtMotivatie = '';

    /** Mededeling aan betrokkenen (AVG art. 34): alleen bij hoog risico. */
    public bool $mededelingBetrokkenen = false;

    public function mount(Incident $incident): void
    {
        // 404 en niet 403: andermans melding bestaat voor deze gebruiker niet —
        // hetzelfde patroon als bij /taken en /beleid.
        abort_unless(
            Recordscope::magAllesZien('incident-afwijkingenbeheer')
                || $incident->gemeld_door_id === auth()->id(),
            404
        );

        $this->incident = $incident;
        $this->status = $incident->status;
        $this->assetId = (string) $incident->gekoppeld_asset_id;
        $this->risicoId = (string) $incident->gekoppeld_risico_id;
        $this->geenAfwijkingReden = $incident->geen_afwijking_reden ?? '';

        $this->kennisnameOp = $incident->kennisname_op?->format('Y-m-d\TH:i') ?? '';
        $this->raaktPersoonsgegevens = self::naarKeuze($incident->raakt_persoonsgegevens);
        $this->isNetwerkInformatieIncident = self::naarKeuze($incident->is_netwerk_informatie_incident);
        $this->externMeldingsplichtig = self::naarKeuze($incident->extern_meldingsplichtig);
        $this->meldplichtMotivatie = $incident->meldplicht_motivatie ?? '';
        $this->mededelingBetrokkenen = $incident->meldingen()
            ->where('grondslag', 'avg')->where('fase', 'betrokkenen')->exists();
    }

    /** Een nullable boolean naar de stringwaarde van een keuzelijst. */
    private static function naarKeuze(?bool $waarde): string
    {
        return $waarde === null ? '' : ($waarde ? '1' : '0');
    }

    /** En terug: '' is "nog niet beoordeeld", niet "nee". */
    private static function uitKeuze(string $waarde): ?bool
    {
        return $waarde === '' ? null : $waarde === '1';
    }

    /** Heeft de organisatie de Cbw-vraag aanstaan? */
    public function cbwPlichtig(): bool
    {
        return (bool) config('meldplicht.cbw_plichtig');
    }

    /**
     * Is er een documentatieplicht op grond van wat er nu in het formulier
     * staat? Stuurt de zichtbaarheid van motivatie en meldvraag.
     */
    public function heeftDocumentatieplicht(): bool
    {
        return $this->raaktPersoonsgegevens === '1'
            || ($this->cbwPlichtig() && $this->isNetwerkInformatieIncident === '1');
    }

    /**
     * Legt de beoordeling van de externe meldplicht vast en maakt de bijbehorende
     * verplichtingen aan.
     *
     * `kennisname_op` is hier bewust corrigeerbaar: wanneer de organisatie kennis
     * kreeg van het incident wordt vaak pas tijdens het onderzoek helder, en een
     * niet te corrigeren ankerpunt levert een permanent verkeerde deadline op.
     * De correctie landt in de audit trail, dus ze is te verantwoorden.
     */
    public function beoordeelMeldplicht(): void
    {
        $this->vereisMuteren();

        // De motivatie en de meldvraag zijn alleen verplicht als er een
        // documentatieplicht is. Zonder raakvlak met een van beide wetten is
        // twee keer "nee" een volledig antwoord — daar is niets te motiveren.
        $plicht = $this->heeftDocumentatieplicht();

        $gevalideerd = $this->validate(array_filter([
            'kennisnameOp' => ['nullable', 'date'],
            'raaktPersoonsgegevens' => ['required', Rule::in(['0', '1'])],
            'isNetwerkInformatieIncident' => $this->cbwPlichtig()
                ? ['required', Rule::in(['0', '1'])]
                : ['nullable'],
            'externMeldingsplichtig' => $plicht ? ['required', Rule::in(['0', '1'])] : ['nullable'],
            'meldplichtMotivatie' => $plicht ? ['required', 'string'] : ['nullable', 'string'],
            'mededelingBetrokkenen' => ['boolean'],
        ]), attributes: [
            'raaktPersoonsgegevens' => 'beoordeling persoonsgegevens',
            'isNetwerkInformatieIncident' => 'beoordeling netwerk- en informatiesystemen',
            'externMeldingsplichtig' => 'beoordeling',
            'meldplichtMotivatie' => 'motivatie',
        ]);

        // Zonder documentatieplicht is er niets te melden: expliciet false, niet
        // null — de vraag ís gesteld en beantwoord.
        $plichtig = $plicht && $gevalideerd['externMeldingsplichtig'] === '1';

        $this->incident->update([
            'kennisname_op' => $gevalideerd['kennisnameOp'] ?: null,
            'raakt_persoonsgegevens' => $gevalideerd['raaktPersoonsgegevens'] === '1',
            // Blijft null als de organisatie niet Cbw-plichtig is: de vraag is
            // dan niet gesteld, en dat is iets anders dan "nee".
            'is_netwerk_informatie_incident' => $this->cbwPlichtig()
                ? $gevalideerd['isNetwerkInformatieIncident'] === '1'
                : null,
            'extern_meldingsplichtig' => $plichtig,
            'meldplicht_beoordeeld_op' => now(),
            'meldplicht_motivatie' => $plicht ? $gevalideerd['meldplichtMotivatie'] : null,
        ]);

        $this->incident->refresh();

        if ($plichtig) {
            Meldplicht::stelVast(
                $this->incident,
                // De grondslag volgt uit de raakvlakvragen, niet uit een eigen keuze.
                $this->incident->meldgrondslagen(),
                $this->mededelingBetrokkenen ? ['avg:betrokkenen'] : [],
            );
        }

        session()->flash('melding', 'Beoordeling externe meldplicht vastgelegd.');
    }

    /** Vinkt één meldverplichting af als gedaan. */
    public function meldingGedaan(int $meldingId): void
    {
        $this->vereisMuteren();

        $melding = $this->incident->meldingen()->findOrFail($meldingId);
        $melding->update(['gemeld_op' => now()]);

        // Het Cbw-eindverslag hangt aan de melding uit art. 27 lid 1, dus zodra
        // die is gedaan is de deadline van het eindverslag pas te bepalen.
        if ($melding->fase === 'melding') {
            $this->incident->refresh();
            Meldplicht::werkAfgeleideTermijnenBij($this->incident);
        }

        session()->flash('melding', 'Melding geregistreerd.');
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['incident-afwijkingenbeheer', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public function opslaan(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'status' => ['required', Rule::in(['gemeld', 'in_onderzoek', 'opgelost', 'gesloten'])],
            'assetId' => ['nullable', Rule::exists('assets', 'id')],
            'risicoId' => ['nullable', Rule::exists('risicos', 'id')],
            'geenAfwijkingReden' => ['nullable', 'string'],
        ], attributes: ['status' => 'status']);

        $sluit = $gevalideerd['status'] === 'gesloten';

        // Een validatiefout en geen 403: dit is geen rechtenkwestie maar een
        // volgordekwestie, en de gebruiker moet weten waarom (§6).
        if ($sluit) {
            $belemmering = $this->incident->belemmeringVoorSluiten($this->geenAfwijkingReden);

            if ($belemmering !== null) {
                $this->addError('status', $belemmering);

                return;
            }
        }

        $attributen = [
            'status' => $gevalideerd['status'],
            'gekoppeld_asset_id' => $gevalideerd['assetId'] !== '' ? (int) $gevalideerd['assetId'] : null,
            'gekoppeld_risico_id' => $gevalideerd['risicoId'] !== '' ? (int) $gevalideerd['risicoId'] : null,
        ];

        if ($sluit) {
            // Niet overschrijven bij een tweede keer opslaan op 'gesloten': het
            // moment van sluiten is het eerste moment, niet het laatste.
            $attributen += [
                'gesloten_op' => $this->incident->gesloten_op ?? now(),
                'gesloten_door_id' => $this->incident->gesloten_door_id ?? auth()->id(),
                'geen_afwijking_reden' => $this->geenAfwijkingReden ?: null,
            ];
        } elseif ($this->incident->gesloten_op !== null) {
            // Heropenen wist de afsluiting, net als bij een afwijking waarvan de
            // toets alsnog 'niet effectief' bleek. Wie sloot en waarom blijft in
            // de audit trail staan.
            $attributen += ['gesloten_op' => null, 'gesloten_door_id' => null];
        }

        $this->incident->update($attributen);

        // Sluiten maakt het ankerpunt van Cbw art. 29 lid 2 bekend: het
        // eindverslag bij een voortdurend incident rekent vanaf de afhandeling.
        if ($sluit) {
            $this->incident->refresh();
            Meldplicht::werkAfgeleideTermijnenBij($this->incident);
        }

        session()->flash('melding', 'Incident bijgewerkt.');
    }

    /** Van melding naar formele afwijking — de ingang van de CAPA-cyclus. */
    public function openAfwijking(): void
    {
        $this->vereisMuteren();

        $afwijking = Afwijking::create([
            'bron' => 'incident',
            'omschrijving' => $this->incident->titel,
            'incident_id' => $this->incident->id,
        ]);

        $this->redirectRoute('afwijkingen.detail', $afwijking, navigate: true);
    }

    public function render()
    {
        return view('livewire.incident-detail', [
            'assets' => $this->magMuteren() ? Asset::orderBy('naam')->pluck('naam', 'id')->all() : [],
            'risicos' => $this->magMuteren() ? Risico::orderBy('titel')->pluck('titel', 'id')->all() : [],
            'afwijkingen' => $this->incident->afwijkingen()->get(),
            // Waarom sluiten nu niet kan — zelfde patroon als bij de afwijking.
            'belemmering' => $this->incident->belemmeringVoorSluiten($this->geenAfwijkingReden),
            'meldingen' => $this->incident->meldingen()
                ->orderBy('grondslag')->orderBy('uiterlijk_op')->get(),
            // Wat het gekoppelde asset over de AVG-vraag zegt: een hint zolang
            // hij openstaat, een tegenspraak zodra er "nee" staat. De waarde uit
            // het formulier, zodat het signaal niet één opslag achterloopt.
            'assetsignaal' => $this->incident->meldplichtsignaalUitAsset(
                self::uitKeuze($this->raaktPersoonsgegevens)
            ),
        ]);
    }
}
