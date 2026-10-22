<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Fakes;

use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Application\SocialLogin\Exceptions\SocialProviderUnavailableException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

final class FakeSocialIdentityProvider implements SocialIdentityProvider
{
    private ?SocialIdentity $identity = null;

    private bool $unavailable = false;

    private bool $redirectFails = false;

    private bool $configured = true;

    public function returnIdentity(
        string $providerUserId,
        string $email,
        bool $emailVerified = true,
        ?string $name = 'Max Muster',
        ?string $hostedDomain = null,
    ): void {
        $this->identity = new SocialIdentity(
            provider: SocialProvider::Google,
            providerUserId: $providerUserId,
            email: $email,
            emailVerified: $emailVerified,
            name: $name,
            hostedDomain: $hostedDomain,
        );
    }

    public function failWithUnavailable(): void
    {
        $this->unavailable = true;
    }

    public function failOnRedirect(): void
    {
        $this->redirectFails = true;
    }

    public function markNotConfigured(): void
    {
        $this->configured = false;
    }

    public function isAvailable(SocialProvider $provider): bool
    {
        return $this->configured;
    }

    public function authorizationUrl(SocialProvider $provider): string
    {
        if ($this->redirectFails || ! $this->configured) {
            throw new SocialProviderUnavailableException('Fake: technischer Detailfehler (darf nicht beim User landen).');
        }

        return 'https://accounts.example.test/oauth?provider='.$provider->value;
    }

    public function fetchIdentity(SocialProvider $provider): SocialIdentity
    {
        if ($this->unavailable || ! $this->configured || $this->identity === null) {
            throw new SocialProviderUnavailableException('Fake: technischer Detailfehler (darf nicht beim User landen).');
        }

        return $this->identity;
    }
}
