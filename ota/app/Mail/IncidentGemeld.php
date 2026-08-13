<?php

namespace App\Mail;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * De enige notificatie in dit blok (implementatie/08 §7). Bewust geen halve
 * notificatielaag: regels, ontvangers en adapters zijn blok 14.
 */
class IncidentGemeld extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Incident $incident) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // De ernst in het onderwerp: wie 's nachts een melding krijgt, moet
            // zonder de mail te openen kunnen zien of het kan wachten.
            subject: 'Incident gemeld ('.$this->incident->ernst.'): '.$this->incident->titel,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.incident-gemeld');
    }
}
