<?php

namespace App\Mail;

use App\Models\Trainingsmodule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Herinnering aan de betrokkene dat een verplichte training (bijna) verloopt of
 * nog niet is afgerond (implementatie/14 §5). Gaat naar de context-ontvanger —
 * de gebruiker wiens training het betreft — niet naar een vaste rol.
 */
class TrainingVerloopt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Trainingsmodule $module) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Training afronden: '.$this->module->titel,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.training-verloopt');
    }
}
