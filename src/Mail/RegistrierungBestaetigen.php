<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Bestätigungslink nach der Selbstregistrierung. Versand nach der Antwort über
 * `NachAntwortBestaetigungsLinkVersand`, nicht über die Queue (Entscheidung 0008).
 */
final class RegistrierungBestaetigen extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $bestaetigungsUrl,
        public readonly int $gueltigMinuten,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('userauth::registrierung.mail.betreff'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'userauth::emails.registrierung_bestaetigen');
    }
}
