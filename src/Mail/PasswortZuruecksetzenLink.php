<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Reset-Link für „Passwort vergessen“. Absender aus `mail.from` des Hosts; Versand nach der Antwort
 * über `NachAntwortResetLinkVersand`, nicht über die Queue (Entscheidung 0008).
 */
final class PasswortZuruecksetzenLink extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $resetUrl,
        public readonly int $gueltigMinuten,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('userauth::password.mail.betreff'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'userauth::emails.passwort_zuruecksetzen');
    }
}
