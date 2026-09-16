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

class GebruikerUitgenodigd extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Gebruiker $gebruiker) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Uitnodiging voor het ISMS',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.gebruiker-uitgenodigd',
            with: [
                'link' => Uitnodiging::link($this->gebruiker),
                'geldigheidDagen' => Uitnodiging::GELDIGHEID_DAGEN,
                // Een extern account kiest geen wachtwoord maar meldt zich aan
                // bij de identiteitsprovider (01j §6.1).
                'idpNaam' => $this->gebruiker->isExtern() ? Configuratie::weergavenaam() : null,
            ],
        );
    }
}
