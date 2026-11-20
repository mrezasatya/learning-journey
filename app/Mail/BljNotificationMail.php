<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BljNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $heading, public string $text, public ?string $link, public string $recipientName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[BLJ] '.$this->heading);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.notification-text', with: ['heading' => $this->heading, 'text' => $this->text, 'link' => $this->link, 'name' => $this->recipientName]);
    }
}
