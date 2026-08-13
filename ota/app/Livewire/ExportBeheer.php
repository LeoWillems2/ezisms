<?php

namespace App\Livewire;

use App\Models\AuditLogregel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Het tweede scherm van het blok `installatiebeheer`: een export van het ISMS
 * wegschrijven naar de uitgang van de installatie (implementatie/01e §3).
 *
 * **De Administrator ziet de inhoud niet.** Hij start de export en leest de
 * bevestiging; het bestand zelf komt op een pad waar alleen iemand met toegang
 * tot de server bij kan. Dat is precies de reden dat dit hier mag staan bij een
 * rol die verder geen enkel ISMS-recht heeft: uitleveren is een handeling aan de
 * installatie, inzien is een recht op de inhoud, en dat blijven twee dingen.
 *
 * Twee begrenzingen die daaruit volgen:
 *   - géén `--met-persoonsgegevens`: de export draagt initialen en rollen;
 *   - géén `--met-bewijs`: bewijsstukken en beleidsdocumenten blijven staan
 *     waar ze staan, en worden alleen bij naam genoemd.
 *
 * Wie een volledige export wil, draait het commando met die vlaggen van de
 * opdrachtregel — dan is er een mens met serversleutels bij, en dat is de
 * bedoeling.
 */
#[Layout('components.layouts.app')]
class ExportBeheer extends Component
{
    /** Staat de bevestigingsvraag open? */
    public bool $bevestigt = false;

    /** Het pad van de zojuist gemaakte export, voor de terugmelding. */
    public string $laatsteExport = '';

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['installatiebeheer', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public static function doelmap(): string
    {
        return rtrim((string) config('export.map'), '/');
    }

    public function vraagBevestiging(): void
    {
        $this->vereisMuteren();
        $this->laatsteExport = '';
        $this->bevestigt = true;
    }

    public function annuleer(): void
    {
        $this->bevestigt = false;
    }

    public function exporteer(): void
    {
        $this->vereisMuteren();

        // De bevestiging is geen formaliteit: een export zet de hele inhoud van
        // het ISMS als leesbare tekst buiten de applicatie, en vanaf dat moment
        // gelden de rechten uit dit systeem er niet meer voor.
        if (! $this->bevestigt) {
            $this->vraagBevestiging();

            return;
        }

        if (! $this->uitgangKlaar()) {
            return;
        }

        $voor = $this->exports()->pluck('pad')->all();

        Artisan::call('isms:exporteer', ['--doel' => self::doelmap()]);

        // Welke map het geworden is, staat niet in een retourwaarde: het
        // commando hangt er zelf een datumstempel aan en bij een tweede run op
        // dezelfde dag ook een tijd. Het verschil met de stand van net is de
        // enige betrouwbare manier om hem aan te wijzen.
        $nieuw = $this->exports()->pluck('pad')->diff($voor);

        $this->laatsteExport = (string) ($nieuw->first() ?? self::doelmap());
        $this->bevestigt = false;

        // Vastleggen dát er geëxporteerd is, en door wie. Niet wát erin staat:
        // dat is het ISMS zelf. Zonder deze regel verlaat de inhoud het systeem
        // zonder spoor, en juist dat is wat een auditor hier wil zien.
        AuditLogregel::legVerzamelingVast(
            blokNaam: 'installatiebeheer',
            entiteitType: 'isms_export',
            actie: 'geexporteerd',
            omschrijving: 'ISMS geëxporteerd naar '.$this->laatsteExport,
            details: [
                'doelmap' => $this->laatsteExport,
                'met_persoonsgegevens' => false,
                'met_bewijs' => false,
            ],
        );

        session()->flash('melding', 'De export staat klaar in '.$this->laatsteExport);
    }

    /**
     * De uitgang moet bestaan en beschrijfbaar zijn vóór het commando begint.
     *
     * `isms:exporteer` maakt de map zelf wel aan, maar alleen als de ouder
     * beschrijfbaar is; lukt dat niet, dan valt het om met een `mkdir():
     * Permission denied` en een stack trace op het scherm. Dat is geen fout van
     * de gebruiker en het is met die melding ook niet op te lossen — vandaar
     * hier, met het commando erbij dat het wél oplost.
     *
     * De map wordt 0750 en niet 0755: hier komt de volledige inhoud van het ISMS
     * te staan. Op de host is dat dezelfde afspraak als bij de Docker-route, waar
     * de bind mount van `www-data` is en lezen `sudo` vraagt.
     */
    private function uitgangKlaar(): bool
    {
        $map = self::doelmap();

        if (! File::isDirectory($map) && ! @mkdir($map, 0750, true) && ! File::isDirectory($map)) {
            session()->flash('fout', "De uitgang {$map} bestaat niet en kan niet worden aangemaakt. "
                .'Laat iemand met servertoegang dit één keer doen: '
                ."sudo install -d -o www-data -g www-data -m 0750 {$map}");

            return false;
        }

        if (! is_writable($map)) {
            session()->flash('fout', "De uitgang {$map} is niet beschrijfbaar voor de applicatie. "
                ."Laat iemand met servertoegang dit rechtzetten: sudo chown www-data:www-data {$map}");

            return false;
        }

        return true;
    }

    /**
     * Wat er al in de uitgang staat. Alleen naam, datum en omvang — de inhoud
     * van een export is ISMS-inhoud en hoort niet in dit scherm.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function exports()
    {
        $map = self::doelmap();

        if (! File::isDirectory($map)) {
            return collect();
        }

        return collect(File::directories($map))
            ->map(fn (string $pad) => [
                'pad' => $pad,
                'naam' => basename($pad),
                'gewijzigd' => File::lastModified($pad),
                'bestanden' => count(File::allFiles($pad)),
            ])
            ->sortByDesc('gewijzigd')
            ->values();
    }

    public function render()
    {
        return view('livewire.export-beheer', [
            'doelmap' => self::doelmap(),
            'rijen' => $this->exports(),
        ]);
    }
}
