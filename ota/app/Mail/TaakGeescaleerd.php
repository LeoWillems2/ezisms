<?php

namespace App\Mail;

use App\Models\Taak;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Een verstreken taak die naar escalatieniveau 2 is getild (implementatie/14
 * §5). Sober, synchroon (geen ShouldQueue) — zoals de andere mails in dit
 * systeem.
 */
class TaakGeescaleerd extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Taak $taak) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Taak geëscaleerd: '.$this->taak->titel,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.taak-geescaleerd');
    }
}
