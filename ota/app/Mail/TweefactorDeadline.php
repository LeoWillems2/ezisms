<?php

namespace App\Mail;

use App\Models\Gebruiker;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Herinnering aan de betrokkene dat zijn tweede factor nog niet is ingesteld
 * (implementatie/01d §9).
 *
 * Gaat naar de context-ontvanger — de gebruiker om wie het gaat — niet naar een
 * vaste rol. Zonder deze mail is de enige waarschuwing de balk in het systeem
 * zelf, en die ziet iemand die twee weken niet inlogt nooit.
 */
class TweefactorDeadline extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Gebruiker $betrokkene,
        public int $dagenResterend,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->dagenResterend > 0
                ? 'Stel uw tweede factor in — nog '.$this->dagenResterend.' dag'.($this->dagenResterend === 1 ? '' : 'en')
                : 'Uw termijn voor de tweede factor is verstreken',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.tweefactor-deadline');
    }
}
