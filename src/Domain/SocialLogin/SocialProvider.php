<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\SocialLogin;

/**
 * Anbieter einer Verknüpfung in `user_social_accounts`. `Oidc` ist der eigene Anmeldedienst (Authelia): Er teilt
 * sich die Verknüpfungen mit dem Social Login, ist aber kein Social-Anbieter (keine Social-Routen, kein
 * „Verbinden“, keine Verknüpfungs-Mail).
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Oidc = 'oidc';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Oidc => 'OpenID Connect',
        };
    }

    public function istSocialLogin(): bool
    {
        return $this !== self::Oidc;
    }

    /**
     * @return list<self>
     */
    public static function socialLogin(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $provider): bool => $provider->istSocialLogin()));
    }
}
