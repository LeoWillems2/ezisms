<?php

namespace App\Mail;

use App\Models\Beveiligingssignaal as Signaal;
use App\Support\Signaalkanaal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Een beveiligingssignaal voor de CISO's, als er geen syslogserver is
 * ingesteld (implementatie/01l §6.4).
 *
 * Geen knop om het account te blokkeren: een mail met een actieknop leert
 * mensen op zulke knoppen te klikken, en dat is precies wat een phishingmail
 * die zich als deze voordoet nodig heeft. De link gaat naar de audit trail, niet
 * naar een handeling.
 */
class Beveiligingssignaal extends Mailable
{
    use Queueable, SerializesModels;

    /** `$proef`: van `isms:beveiligingssignaal-proef`; er is niets gebeurd. */
    public function __construct(public Signaal $signaal, public bool $proef = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->proef
                ? '[EzISMS] Proefsignaal van de beveiligingsbewaking'
                : '[EzISMS] Beveiligingssignaal: '.Signaal::SOORTEN[$this->signaal->soort])
                .' ('.Signaalkanaal::installatie().')',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.beveiligingssignaal');
    }
}
