<?php

namespace App\Livewire;

use App\Models\Gebruiker;
use App\Support\Adreswijziging;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * De bevestiging van een adreswijziging, op het nieuwe adres
 * (implementatie/01h §7).
 *
 * **Een scherm met een knop, en geen route die bij het openen al muteert.** De
 * linkscanners van mailfilters volgen links in binnenkomende post; bij een
 * muterende `GET` bevestigt de beveiligingssoftware van de ontvanger de
 * wijziging in plaats van de ontvanger. Zelfde reden dat
 * {@see UitnodigingAccepteren} een scherm is.
 *
 * Na afloop wordt er **niet** ingelogd: dit scherm bewijst controle over een
 * postbus, niet kennis van een wachtwoord.
 */
#[Layout('components.layouts.auth')]
class BevestigAdreswijziging extends Component
{
    public Gebruiker $gebruiker;

    /** Vastgehouden voor het slotscherm: na de bevestiging is de kolom leeg. */
    public string $nieuwEmail = '';

    public bool $bevestigd = false;

    public ?string $mislukt = null;

    public function mount(Gebruiker $gebruiker, string $token): void
    {
        abort_unless(
            Adreswijziging::tokenIsGeldig($gebruiker, $token),
            403,
            'Deze bevestigingslink is niet meer geldig.'
        );
        abort_unless(
            $gebruiker->status === 'actief',
            403,
            'Dit account is niet actief; neem contact op met de CISO.'
        );

        $this->gebruiker = $gebruiker;
        $this->nieuwEmail = $gebruiker->nieuw_email;
    }

    public function bevestigen(): void
    {
        // Nog een keer, want tussen het openen van het scherm en het indrukken
        // van de knop kan er van alles gebeurd zijn: de CISO trekt de wijziging
        // in, vraagt een andere aan, of het account wordt geblokkeerd.
        $this->gebruiker->refresh();

        if (! $this->gebruiker->adreswijzigingLoopt()
            || $this->gebruiker->nieuw_email !== $this->nieuwEmail
            || $this->gebruiker->status !== 'actief') {
            $this->mislukt = 'Deze wijziging is niet meer geldig. Vraag de CISO om een nieuwe aanvraag.';

            return;
        }

        // Het adres kan in de tussentijd door een ander account zijn ingenomen;
        // de aanvraag toetste dat, maar dat was toen (§8).
        $bezet = Gebruiker::where('email', $this->nieuwEmail)
            ->whereKeyNot($this->gebruiker->getKey())
            ->exists();

        if ($bezet) {
            $this->mislukt = 'Dit e-mailadres is inmiddels in gebruik bij een ander account. '
                .'Neem contact op met de CISO.';

            return;
        }

        // Zonder dit schrijft Auditeerbaar 'Systeem (geplande taak)' als
        // handelende gebruiker — er is geen sessie op een publieke link
        // (Auditeerbaar.php, `auth()->user()`). `onceUsingId` zet de gebruiker
        // voor dit ene verzoek en laat geen sessie achter. Dat de houder van de
        // link de accountgebruiker is, is precies wat deze bevestiging aantoont.
        Auth::onceUsingId($this->gebruiker->getKey());

        $this->gebruiker->bevestigAdreswijziging();

        $this->bevestigd = true;
    }

    public function render()
    {
        return view('livewire.bevestig-adreswijziging');
    }
}
