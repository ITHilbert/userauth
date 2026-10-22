<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class SocialLinkConfirmation extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $confirmUrl,
        public readonly string $providerLabel,
        public readonly int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('userauth::social.mail.confirmation_subject', ['provider' => $this->providerLabel]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'userauth::emails.social_link_confirmation');
    }
}
