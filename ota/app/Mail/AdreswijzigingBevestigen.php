<?php

namespace App\Mail;

use App\Models\Gebruiker;
use App\Support\Adreswijziging;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Naar het **nieuwe** adres (implementatie/01h §6). Draagt de link; zolang er
 * niet op is gedrukt verandert er niets aan het account.
 */
class AdreswijzigingBevestigen extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Gebruiker $gebruiker) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bevestig uw nieuwe e-mailadres voor het ISMS',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.adreswijziging-bevestigen',
            with: [
                'link' => Adreswijziging::link($this->gebruiker),
                'geldigheidDagen' => Adreswijziging::GELDIGHEID_DAGEN,
                'nieuwEmail' => $this->gebruiker->nieuw_email,
            ],
        );
    }
}
