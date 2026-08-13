<?php

namespace App\Mail;

use App\Models\Taak;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Bericht aan de eigenaar dat zijn stap in een reeks aan de beurt is
 * (implementatie/07b §9). Gaat naar de context-ontvanger — de eigenaar van de
 * stap — en niet naar een vaste rol, net als `TrainingVerloopt`.
 */
class StapActueel extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Taak $stap) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Stap aan de beurt: '.$this->stap->titel,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.stap-actueel');
    }
}
