<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Str;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\PendingSocialLinkRepository;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;
use ITHilbert\UserAuth\Mail\SocialLinkConfirmation;

/**
 * Legt einen offenen Verknüpfungs-Link an (nur Hash gespeichert) und verschickt den Bestätigungslink.
 */
final class IssueSocialLinkConfirmation
{
    public function __construct(
        private readonly PendingSocialLinkRepository $pendingLinks,
        private readonly SocialLoginSettings $settings,
        private readonly Mailer $mailer,
        private readonly UrlGenerator $urls,
    ) {}

    public function handle(LocalUser $user, SocialIdentity $identity): void
    {
        $token = Str::random(64);
        $ttl = $this->settings->pendingLinkTtlMinutes();

        $this->pendingLinks->replace(
            $user->id,
            $identity,
            hash('sha256', $token),
            CarbonImmutable::now()->addMinutes($ttl),
        );

        $this->mailer->to($user->email)->send(new SocialLinkConfirmation(
            confirmUrl: $this->urls->route('userauth.social.confirm', $token),
            providerLabel: $identity->provider->label(),
            ttlMinutes: $ttl,
        ));
    }
}
