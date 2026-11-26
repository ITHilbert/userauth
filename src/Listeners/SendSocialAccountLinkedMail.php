<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Listeners;

use Illuminate\Contracts\Mail\Mailer;
use ITHilbert\UserAuth\Domain\SocialLogin\Events\SocialAccountLinked;
use ITHilbert\UserAuth\Mail\SocialAccountLinked as SocialAccountLinkedMail;

final class SendSocialAccountLinkedMail
{
    public function __construct(private readonly Mailer $mailer) {}

    public function handle(SocialAccountLinked $event): void
    {
        // Verknüpfung mit dem eigenen Anmeldedienst (OIDC): keine Mail, der Benutzer hat sich dort selbst angemeldet
        if (! $event->provider->istSocialLogin()) {
            return;
        }

        $this->mailer->to($event->userEmail)->send(new SocialAccountLinkedMail($event->provider->label()));
    }
}
