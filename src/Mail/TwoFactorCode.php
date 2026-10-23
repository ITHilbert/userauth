<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Zwei-Faktor-Code. Absender aus `mail.from` des Hosts; Versand synchron über `MailZweiFaktorCodeVersand`.
 */
final class TwoFactorCode extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $gueltigMinuten,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('userauth::zwei_faktor.mail.betreff'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'userauth::emails.two_factor_code');
    }
}
