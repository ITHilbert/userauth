<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

use Illuminate\Contracts\Events\Dispatcher;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialAccountRepository;
use ITHilbert\UserAuth\Domain\SocialLogin\Events\SocialAccountUnlinked;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

/**
 * Anwendungsfall: Ein Nutzer trennt seine eigene Verknüpfung.
 */
final class DisconnectSocialAccount
{
    public function __construct(
        private readonly SocialAccountRepository $accounts,
        private readonly Dispatcher $events,
    ) {}

    public function handle(int|string $userId, string $userEmail, SocialProvider $provider): bool
    {
        if (! $this->accounts->unlink($userId, $provider)) {
            return false;
        }

        $this->events->dispatch(new SocialAccountUnlinked($userId, $userEmail, $provider));

        return true;
    }
}
