<?php

namespace App\Support;

use App\Models\Gebruiker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * De uitnodiging als tekstbestand, voor een installatie zonder mailkanaal
 * (implementatie/01i §5).
 *
 * Platte tekst en geen docx zoals de schermkopie: dit is geen document voor een
 * dossier maar een briefje dat op een USB-stick meegaat, wordt afgedrukt of in
 * een chatvenster wordt geplakt. Het moet overal te openen zijn.
 *
 * De klasse maakt de tekst en niets anders. De vastlegging dát er uitgereikt is
 * hoort bij de handeling en staat daarom in de component (§6).
 */
final class Uitnodigingsbrief
{
    private function __construct(private readonly Gebruiker $gebruiker) {}

    public static function voor(Gebruiker $gebruiker): self
    {
        return new self($gebruiker);
    }

    public function tekst(): string
    {
        // Eén keer ophalen: de link is een `temporarySignedRoute` waarvan de
        // vervaldatum uit dit moment volgt, dus de datum hieronder moet uit
        // dezelfde berekening komen als de link zelf.
        $link = Uitnodiging::link($this->gebruiker);
        $geldigTot = Carbon::now()->addDays(Uitnodiging::GELDIGHEID_DAGEN);

        $regels = [
            'Uitnodiging voor het ISMS van '.config('app.name'),
            '',
            $this->veld('Uitgereikt op', Carbon::now()->lokaal()->format('d-m-Y H:i')),
            $this->veld('Uitgereikt door', auth()->user()?->naam ?? '—'),
            '',
            $this->veld('Naam', $this->gebruiker->naam),
            $this->veld('E-mailadres', $this->gebruiker->email),
            $this->veld('Rol(len)', $this->gebruiker->rollen->pluck('naam')->join(', ') ?: '—'),
            $this->veld('Afdeling', $this->gebruiker->afdeling?->naam ?? '—'),
            '',
            'Uitnodigingslink (geldig tot '.$geldigTot->lokaal()->format('d-m-Y H:i').'):',
            $link,
            '',
            'Open de link, stel een wachtwoord in en het account is actief. De link',
            'werkt eenmalig: zodra het wachtwoord is ingesteld, vervalt hij.',
            '',
            'Behandel dit bestand als een wachtwoord. Wie het heeft, kan dit account',
            'activeren.',
        ];

        return implode("\n", $regels)."\n";
    }

    public function bestandsnaam(): string
    {
        return 'uitnodiging-'.Str::slug($this->gebruiker->naam).'-'.Carbon::now()->format('Ymd').'.txt';
    }

    private function veld(string $label, string $waarde): string
    {
        return str_pad($label, 16).': '.$waarde;
    }
}
