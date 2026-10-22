<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

/**
 * Anbieter, die aktiviert UND konfiguriert sind (für Login-Button und Verbinden-Link).
 */
final class ListAvailableSocialProviders
{
    public function __construct(
        private readonly SocialLoginSettings $settings,
        private readonly SocialIdentityProvider $identityProvider,
    ) {}

    /**
     * @return list<string> Provider-Werte, z. B. ['google']
     */
    public function handle(): array
    {
        if (! $this->settings->enabled()) {
            return [];
        }

        $available = [];

        foreach (SocialProvider::cases() as $provider) {
            if ($this->settings->isProviderEnabled($provider) && $this->identityProvider->isAvailable($provider)) {
                $available[] = $provider->value;
            }
        }

        return $available;
    }
}
