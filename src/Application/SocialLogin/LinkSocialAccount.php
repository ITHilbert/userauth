<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

use Illuminate\Contracts\Events\Dispatcher;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialAccountRepository;
use ITHilbert\UserAuth\Domain\SocialLogin\Events\SocialAccountLinked;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialAccountTakenException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;

/**
 * Gemeinsamer Schritt aller Verknüpfungs-Wege: speichern + Domain-Event.
 */
final class LinkSocialAccount
{
    public function __construct(
        private readonly SocialAccountRepository $accounts,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @throws SocialAccountTakenException
     */
    public function handle(LocalUser $user, SocialIdentity $identity): void
    {
        $this->accounts->link($user->id, $identity);

        $this->events->dispatch(new SocialAccountLinked($user->id, $user->email, $identity->provider));
    }
}
