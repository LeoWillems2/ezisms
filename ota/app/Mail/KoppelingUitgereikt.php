<?php

namespace App\Mail;

use App\Models\Gebruiker;
use App\Support\Oidc\Configuratie;
use App\Support\Uitnodiging;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * De koppellink voor een actief account dat (opnieuw) aan de externe
 * identiteitsprovider gekoppeld moet worden (implementatie/01j §9.1). Geen
 * uitnodiging: het account bestaat al en heeft historie.
 */
class KoppelingUitgereikt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Gebruiker $gebruiker) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Koppel uw ISMS-account aan '.Configuratie::weergavenaam(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.koppeling-uitgereikt',
            with: [
                'link' => Uitnodiging::link($this->gebruiker),
                'geldigheidDagen' => Uitnodiging::GELDIGHEID_DAGEN,
                'idpNaam' => Configuratie::weergavenaam(),
            ],
        );
    }
}
