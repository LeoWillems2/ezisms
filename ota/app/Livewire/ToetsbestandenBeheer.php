<?php

namespace App\Livewire;

use App\Models\Toetsopdracht;
use App\Support\ExterneBronnen;
use App\Support\ToetsBestanden;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Het enige scherm van het blok `installatiebeheer` (implementatie/01e §2.2).
 *
 * Toetsbestanden plaatsen was tot 01e een serverklus: het bestand moest via SSH
 * in `public/toetsen/` gezet worden, en de CISO heeft wél de bouwhulp maar niet
 * de map. Dit scherm haalt die stap weg, en de rol die erbij hoort — de
 * Administrator — heeft daarbuiten geen enkel recht in het ISMS.
 *
 * De inhoud van een toets is niet te vertrouwen: het is door een mens geleverde
 * HTML met JavaScript, en dit is het minst vertrouwde account in het systeem.
 * De afscherming zit niet hier maar bij het uitserveren (`Toetsrespons`); wat
 * hier gebeurt is de opslag begrenzen.
 */
#[Layout('components.layouts.app')]
class ToetsbestandenBeheer extends Component
{
    use WithFileUploads;

    /** Ruim genoeg voor een toets met ingebakken plaatjes; zie 01e §2.2. */
    public const MAX_KB = 8192;

    public $bestand;

    public bool $toontFormulier = false;

    /** Het bestand waarvan de vervanging bevestigd moet worden. */
    public string $bevestigOverschrijven = '';

    /** Het bestand waarvan de verwijdering bevestigd moet worden. */
    public string $bevestigVerwijderen = '';

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['installatiebeheer', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public function openFormulier(): void
    {
        $this->vereisMuteren();
        $this->reset(['bestand', 'bevestigOverschrijven']);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->reset(['bestand', 'bevestigOverschrijven', 'toontFormulier']);
    }

    public function uploaden(): void
    {
        $this->vereisMuteren();

        $this->validate([
            // Alleen HTML, en alleen op de extensie: `mimes:html` laat de
            // browser meebepalen wat dit bestand is, en dat is precies de
            // partij die we hier niet vertrouwen. Wat erin staat wordt niet
            // gecontroleerd — dat kán niet zinvol, en daarom draait een toets
            // in een sandbox (01e §1.4).
            'bestand' => ['required', 'file', 'extensions:html', 'max:'.self::MAX_KB],
        ], attributes: ['bestand' => 'toetsbestand']);

        $naam = $this->veiligeNaam($this->bestand->getClientOriginalName());

        // Overschrijven vraagt een bevestiging: een toets die al is uitgezet,
        // verandert daarmee onder de deelnemers vandaan.
        if (ToetsBestanden::bestaat($naam) && $this->bevestigOverschrijven !== $naam) {
            $this->bevestigOverschrijven = $naam;

            return;
        }

        $inhoud = (string) $this->bestand->get();

        Storage::disk(ToetsBestanden::DISK)->put($naam, $inhoud);

        $this->sluitFormulier();
        session()->flash('melding', "Toets '{$naam}' geplaatst.");

        /** @var list<string> $meldingen */
        $meldingen = [];

        // Een toets die de terugmeldfunctie niet aanroept, werkt voor de
        // deelnemer volledig normaal en registreert niets — er is geen fout, geen
        // logregel, en de taak blijft openstaan. Dat is op 11-08-2026 precies
        // misgegaan bij drie toetsen die een zelfverzonnen `onQuizPassed`
        // aanriepen. Een blokkade is te streng (iemand kan zelf een fetch
        // schrijven), maar stil blijven is te laf.
        if (! str_contains($inhoud, 'onQuizVoltooid')) {
            $meldingen[] = "In '{$naam}' komt onQuizVoltooid niet voor. "
                .'Deze toets meldt de uitslag waarschijnlijk niet terug aan het ISMS: '
                .'de deelnemer maakt hem, ziet geen fout, en zijn taak blijft openstaan. '
                .'Laat de maker de functie uit de bouwhulp toevoegen én aanroepen.';
        }

        // Tweede controle, zelfde vorm en zelfde afweging (10b §6). Hier is de
        // blokkade er al — de CSP van `Toetsrespons` laat geen externe bronnen
        // door — dus deze melding voorkomt alleen dat de Administrator er via een
        // klagende deelnemer achter komt.
        $hosts = ExterneBronnen::hosts($inhoud);

        if ($hosts !== []) {
            $meldingen[] = "In '{$naam}' staan verwijzingen naar ".implode(', ', $hosts).'. '
                .'Die worden bij het uitserveren geblokkeerd: de deelnemer krijgt een pagina '
                .'zonder opmaak. Laat de maker ze in het bestand zelf opnemen — de bouwhulp '
                .'heeft een skelet dat dat al goed doet.';
        }

        if ($meldingen !== []) {
            session()->flash('fout', implode(' ', $meldingen));
        }
    }

    public function verwijder(string $bestand): void
    {
        $this->vereisMuteren();

        $bestand = basename($bestand);

        // Met opslag op de schijf is `toets_bestand` een verwijzing en geen
        // foreign key, dus deze controle staat hier in plaats van in het schema
        // (01e §2.2). Alleen het aantal in de melding en niet de namen: de
        // Administrator hoort niet te zien wie welke toets open heeft staan.
        // `gezakt` telt mee als openstaand: een deelnemer mag het opnieuw
        // proberen, en dan moet het bestand er nog zijn. Alleen `geslaagd` is af.
        $openstaand = Toetsopdracht::where('toets_bestand', $bestand)
            ->where('status', '!=', 'geslaagd')
            ->count();

        if ($openstaand > 0) {
            $this->bevestigVerwijderen = '';
            session()->flash('fout', "Deze toets staat nog bij {$openstaand} deelnemer(s) open. "
                .'Zet hem eerst stop of wacht tot die opdrachten afgerond zijn.');

            return;
        }

        if ($this->bevestigVerwijderen !== $bestand) {
            $this->bevestigVerwijderen = $bestand;

            return;
        }

        Storage::disk(ToetsBestanden::DISK)->delete($bestand);

        $this->bevestigVerwijderen = '';
        session()->flash('melding', "Toets '{$bestand}' verwijderd.");
    }

    /**
     * De bestandsnaam saneren. Dit is de enige route waar een bestand het
     * systeem binnenkomt vanaf het minst vertrouwde account, dus geen paden,
     * geen `..`, en verder alleen wat een bestandsnaam hoort te zijn.
     */
    private function veiligeNaam(string $naam): string
    {
        $naam = basename($naam);
        $naam = (string) preg_replace('/[^A-Za-z0-9._-]/', '-', $naam);
        $naam = ltrim($naam, '.-');

        return $naam !== '' ? $naam : 'toets.html';
    }

    public function render()
    {
        $disk = Storage::disk(ToetsBestanden::DISK);

        $rijen = collect(ToetsBestanden::beschikbaar())
            ->map(fn (string $titel, string $bestand) => [
                'bestand' => $bestand,
                'titel' => $titel,
                'grootte' => $disk->size($bestand),
                'gewijzigd' => $disk->lastModified($bestand),
                // De voorbeeldroute zit achter `bewustzijn-training`; de
                // Administrator heeft die niet en krijgt dus geen link.
                'voorbeeld' => Gate::allows('heeft-niveau', ['bewustzijn-training', 'muteren'])
                    ? route('toetsen.voorbeeld', $bestand)
                    : null,
            ])
            ->values();

        return view('livewire.toetsbestanden-beheer', [
            'rijen' => $rijen,
            // De bouwhulp blijft bij wie toetsen uitzet: die gaat over de inhoud
            // van een toets, niet over het plaatsen ervan (01e §2.2).
            'magBouwhulp' => Gate::allows('heeft-niveau', ['bewustzijn-training', 'muteren']),
        ]);
    }
}
