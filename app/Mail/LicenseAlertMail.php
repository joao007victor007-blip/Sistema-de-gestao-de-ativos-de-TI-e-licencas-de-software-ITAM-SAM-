<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class LicenseAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $expiring,
        public Collection $expired,
        public Collection $renewing,
    ) {
    }

    public function envelope(): Envelope
    {
        $total = $this->expiring->count() + $this->expired->count() + $this->renewing->count();

        return new Envelope(
            subject: $total === 1
                ? 'Intranet de TI: aviso sobre 1 licença'
                : "Intranet de TI: aviso sobre {$total} licenças",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.license-alert');
    }
}