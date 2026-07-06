<?php

namespace App\Mail;

use App\Models\Communication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** An outbound matter communication, sent as a real email. */
class CommunicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Communication $communication)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->communication->subject ?: 'Correspondence from your IP attorneys',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.communication', with: [
            'communication' => $this->communication,
        ]);
    }
}
