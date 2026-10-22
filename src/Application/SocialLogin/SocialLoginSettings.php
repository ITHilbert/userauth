<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

use Illuminate\Contracts\Config\Repository;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

/**
 * Liest die Social-Login-Konfiguration erst zur Laufzeit (nicht beim Boot).
 */
final class SocialLoginSettings
{
    public function __construct(private readonly Repository $config) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('userauth.social.enabled', false);
    }

    public function isProviderEnabled(SocialProvider $provider): bool
    {
        return in_array($provider->value, (array) $this->config->get('userauth.social.providers', []), true);
    }

    public function allowRegistration(): bool
    {
        return (bool) $this->config->get('userauth.social.allow_registration', false);
    }

    public function defaultRole(): string
    {
        return (string) $this->config->get('userauth.social.default_role', 'user');
    }

    /**
     * @return list<string>
     */
    public function allowedDomains(): array
    {
        return array_values(array_map(strval(...), (array) $this->config->get('userauth.social.allowed_domains', [])));
    }

    public function pendingLinkTtlMinutes(): int
    {
        return (int) $this->config->get('userauth.social.pending_link_ttl_minutes', 60);
    }
}
