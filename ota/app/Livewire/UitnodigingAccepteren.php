<?php

namespace App\Livewire;

use App\Models\Gebruiker;
use App\Support\Uitnodiging;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Publiek bereikbaar, maar alleen met een geldige signed URL én een token dat
 * bij de huidige (tijdelijke) wachtwoord-hash hoort.
 * Zie implementatie/01-identity-access.md §8.
 *
 * **Drie stappen sinds 03-08-2026** (implementatie/01d §13): wachtwoord, tweede
 * factor, herstelcodes. Een nieuwe gebruiker zit hier al achter zijn scherm met
 * de uitnodiging open, en dat is het goedkoopste moment om de app te koppelen.
 * De respijtperiode van veertien dagen blijft bestaan voor de accounts die er al
 * wáren toen 2FA werd aangezet — dat is een migratiemechanisme, geen blijvende
 * eigenschap.
 *
 * Alles gebeurt in dit ene component en niet via een doorverwijzing: de
 * uitnodigingslink is ná het instellen van het wachtwoord verbruikt (de token
 * hangt aan de wachtwoord-hash), dus een tweede pagina zou onbereikbaar zijn.
 */
#[Layout('components.layouts.auth')]
class UitnodigingAccepteren extends Component
{
    public Gebruiker $gebruiker;

    public string $wachtwoord = '';

    public string $wachtwoord_bevestiging = '';

    public string $code = '';

    /** wachtwoord → tweefactor → klaar */
    public string $stap = 'wachtwoord';

    /** @var list<string>|null */
    public ?array $herstelcodes = null;

    public function mount(Gebruiker $gebruiker, string $token): void
    {
        // Een verlopen of hergebruikte link moet een begrijpelijke melding
        // opleveren in plaats van een kale 403 (§8).
        abort_unless(Uitnodiging::tokenIsGeldig($gebruiker, $token), 403, 'Deze uitnodigingslink is niet meer geldig.');
        abort_unless($gebruiker->status === 'uitgenodigd', 403, 'Deze uitnodiging is al gebruikt.');

        $this->gebruiker = $gebruiker;
    }

    public function opslaan(EnableTwoFactorAuthentication $inschakelen): void
    {
        $this->validate([
            'wachtwoord' => ['required', 'string', 'confirmed:wachtwoord_bevestiging', Password::defaults()],
        ], attributes: ['wachtwoord' => 'wachtwoord']);

        $this->gebruiker->update([
            'wachtwoord' => $this->wachtwoord,
            'status' => 'actief',
            // Het accepteren van de uitnodiging ís het bewijs dat post op dit
            // adres aankomt (01g §1). Geen enkel scherm leest deze kolom vandaag;
            // hij wordt tóch gevuld omdat het feit achteraf niet te
            // reconstrueren is — wie later een adreswijziging voor actieve
            // accounts bouwt, heeft precies dit nodig om "bewezen adres" van
            // "nooit gecontroleerd" te onderscheiden.
            'email_geverifieerd_op' => now(),
        ]);

        $this->reset(['wachtwoord', 'wachtwoord_bevestiging']);

        // Het account is nu bruikbaar, ook als de gebruiker hierna wegklikt. Dat
        // is opzet: een account dat pas ná het koppelen kan inloggen, is een
        // account waar niemand meer bij kan als het koppelen misgaat. Wie
        // afhaakt, valt terug op de respijtperiode.
        if (! config('tweefactor.afdwingen')) {
            $this->naarLogin('Uw wachtwoord is ingesteld. U kunt nu inloggen.');

            return;
        }

        $inschakelen($this->gebruiker, force: true);
        $this->gebruiker->refresh();

        $this->stap = 'tweefactor';
    }

    public function bevestigen(ConfirmTwoFactorAuthentication $bevestigen): void
    {
        $this->validate(['code' => ['required', 'string']], attributes: ['code' => 'code']);

        try {
            $bevestigen($this->gebruiker, $this->code);
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'code' => 'Die code klopt niet. Controleer of de tijd op uw telefoon goed staat.',
            ]);
        }

        $this->reset('code');
        $this->herstelcodes = $this->gebruiker->fresh()->recoveryCodes();
        $this->stap = 'klaar';
    }

    /**
     * "Ik heb mijn telefoon nu niet bij me." Geen ontsnapping maar dezelfde weg
     * die het wegklikken van dit scherm ook oplevert — expliciet en met de
     * termijn erbij, zodat niemand denkt dat het hiermee geregeld is.
     */
    public function laterInstellen(): void
    {
        $this->naarLogin('Uw wachtwoord is ingesteld. Stel de tweede factor in bij uw eerste aanmelding.');
    }

    public function naarLogin(string $melding = 'U kunt nu inloggen.'): void
    {
        session()->flash('status', $melding);

        $this->redirectRoute('login', navigate: true);
    }

    public function render()
    {
        return view('livewire.uitnodiging-accepteren');
    }
}
