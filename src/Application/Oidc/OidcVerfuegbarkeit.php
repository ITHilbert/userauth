<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Oidc;

use ITHilbert\UserAuth\Application\Oidc\Contracts\OidcIdentityProvider;

/**
 * Ob die OIDC-Anmeldung angeboten wird: eingeschaltet und konfiguriert (Knopf, Auto-Redirect, Start des Ablaufs).
 */
final class OidcVerfuegbarkeit
{
    public function __construct(
        private readonly OidcSettings $settings,
        private readonly OidcIdentityProvider $provider,
    ) {}

    public function verfuegbar(): bool
    {
        return $this->settings->enabled() && $this->provider->isConfigured();
    }

    /**
     * Login-Seite leitet Gäste direkt zum Anbieter weiter (außer mit `?lokal=1`).
     */
    public function automatischWeiterleiten(): bool
    {
        return $this->settings->autoRedirect() && $this->verfuegbar();
    }
}
