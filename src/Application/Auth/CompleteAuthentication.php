<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use ITHilbert\UserAuth\Application\Auth\Contracts\OffeneAnmeldungen;
use ITHilbert\UserAuth\Application\Auth\Contracts\Sitzungsanmeldung;
use ITHilbert\UserAuth\Domain\Auth\OffeneAnmeldung;

/**
 * Schritt nach bestandenem ersten Faktor (Passwort oder Social Login; OIDC siehe ohneZweitenFaktor()).
 * Ohne 2FA: sofort anmelden (AK21). Mit 2FA: noch nicht anmelden, sondern eine offene Anmeldung in der Sitzung
 * beginnen und den Code senden; „Angemeldet bleiben“ wirkt erst nach bestandener 2FA (AK14).
 */
final class CompleteAuthentication
{
    public function __construct(
        private readonly Repository $config,
        private readonly Sitzungsanmeldung $sitzung,
        private readonly OffeneAnmeldungen $anmeldungen,
        private readonly ZweiFaktorCodeSenden $codeSenden,
    ) {}

    public function handle(Authenticatable $user, bool $angemeldetBleiben = false): AuthenticationNextStep
    {
        if (! $this->config->get('userauth.two_factor_enabled')) {
            $this->sitzung->anmelden($user, $angemeldetBleiben);

            return AuthenticationNextStep::Done;
        }

        $anmeldung = OffeneAnmeldung::beginnen($user->getAuthIdentifier(), $angemeldetBleiben, $this->anmeldungen->zielEntnehmen());
        $this->anmeldungen->beginnen($anmeldung);
        if (! $this->codeSenden->handle($anmeldung, $user)) {
            return AuthenticationNextStep::CodeNichtVersendet;
        }

        return AuthenticationNextStep::TwoFactorRequired;
    }

    /**
     * Anmeldung über den eigenen Anmeldedienst (OIDC): Der Anbieter hat die Anmeldung samt seiner Faktoren
     * geprüft, daher sofort anmelden – auch bei `two_factor_enabled` (OIDC AK13). Erneuert die Sitzungs-ID.
     */
    public function ohneZweitenFaktor(Authenticatable $user): void
    {
        $this->sitzung->anmelden($user, false);
    }
}
