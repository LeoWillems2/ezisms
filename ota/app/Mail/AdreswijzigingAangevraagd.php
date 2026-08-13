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
 * Naar het **oude** adres, op het moment van aanvragen (implementatie/01h §6).
 *
 * Draagt bewust géén link. Dit bericht is de detectie, niet de handeling: wie
 * kwaadwillend het nieuwe adres beheert bevestigt binnen seconden, dus een
 * "dit was ik niet"-knop zou zelden op tijd komen en levert wel een
 * niet-geauthenticeerde route op die de toestand wijzigt. Het juiste antwoord op
 * een wijziging die niet klopt is Blokkeren (01f), en dat is een CISO-handeling.
 */
class AdreswijzigingAangevraagd extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Gebruiker $gebruiker,
        public string $nieuwEmail,
        public string $aangevraagdDoor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Er is een wijziging van uw e-mailadres aangevraagd',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.adreswijziging-aangevraagd',
            with: [
                'gemaskeerd' => Adreswijziging::gemaskeerd($this->nieuwEmail),
                'geldigheidDagen' => Adreswijziging::GELDIGHEID_DAGEN,
            ],
        );
    }
}
